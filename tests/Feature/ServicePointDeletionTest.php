<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\ServicePoints\DeleteServicePointAction;
use App\Enums\AuditLogAction;
use App\Enums\QrCodeStatus;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleViolation;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as ServicePointsIndex;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\OrganizationUser;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use Database\Seeders\DemoOrganizationCrudSeeder;
use Database\Seeders\DemoRestaurantSeeder;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('authorized manager soft deletes an unused service point', function () {
    [$owner, , , $branch] = createServicePointDeletionContext();
    $servicePoint = ServicePoint::factory()->for($branch)->blocked()->create([
        'name' => 'Unused deletion table',
        'internal_code' => 'DELETE-UNUSED',
    ]);

    app(DeleteServicePointAction::class)->handle($owner, $branch, $servicePoint);

    expect(ServicePoint::query()->whereKey($servicePoint->id)->exists())->toBeFalse()
        ->and(ServicePoint::withTrashed()->whereKey($servicePoint->id)->firstOrFail()->trashed())->toBeTrue();
});

test('service point with a direct active table session cannot be deleted', function () {
    [$owner, , , $branch] = createServicePointDeletionContext();
    $servicePoint = ServicePoint::factory()->for($branch)->create();
    TableSession::factory()->forServicePoint($servicePoint)->active()->create();

    expect(fn () => app(DeleteServicePointAction::class)->handle($owner, $branch, $servicePoint))
        ->toThrow(BusinessRuleViolation::class);

    expect($servicePoint->fresh())->not->toBeNull();
});

test('service point linked to an active table session cannot be deleted', function () {
    [$owner, , , $branch] = createServicePointDeletionContext();
    $primaryServicePoint = ServicePoint::factory()->for($branch)->create();
    $linkedServicePoint = ServicePoint::factory()->for($branch)->create();
    $tableSession = TableSession::factory()->forServicePoint($primaryServicePoint)->active()->create();
    TableSessionServicePoint::factory()
        ->forTableSessionAndServicePoint($tableSession, $linkedServicePoint)
        ->linked()
        ->create();

    expect(fn () => app(DeleteServicePointAction::class)->handle($owner, $branch, $linkedServicePoint))
        ->toThrow(BusinessRuleViolation::class);

    expect($linkedServicePoint->fresh())->not->toBeNull();
});

test('service point referenced by an active order cannot be deleted after its session closes', function () {
    [$owner, , , $branch] = createServicePointDeletionContext();
    $servicePoint = ServicePoint::factory()->for($branch)->blocked()->create();
    $closedSession = TableSession::factory()->forServicePoint($servicePoint)->closed()->create();
    Order::factory()->forTableSession($closedSession)->paymentRequested()->create();

    expect(fn () => app(DeleteServicePointAction::class)->handle($owner, $branch, $servicePoint))
        ->toThrow(BusinessRuleViolation::class);

    expect($servicePoint->fresh())->not->toBeNull();
});

test('deletion disables the active qr and preserves qr order and audit history', function () {
    [$owner, $organization, , $branch] = createServicePointDeletionContext();
    $servicePoint = ServicePoint::factory()->for($branch)->blocked()->create();
    $qrCode = QrCode::factory()->forServicePoint($servicePoint)->active()->create();
    $closedSession = TableSession::factory()->forServicePoint($servicePoint)->closed()->create();
    $order = Order::factory()->forTableSession($closedSession)->closed()->create();
    $historicalAudit = AuditLog::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'user_id' => $owner->id,
        'entity_type' => 'service_point',
        'entity_id' => $servicePoint->id,
    ]);

    app(DeleteServicePointAction::class)->handle($owner, $branch, $servicePoint);

    expect($qrCode->fresh()->status)->toBe(QrCodeStatus::Disabled)
        ->and($qrCode->fresh()->active_service_point_id)->toBeNull()
        ->and(QrCode::query()->whereKey($qrCode->id)->exists())->toBeTrue()
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->whereKey($historicalAudit->id)->exists())->toBeTrue()
        ->and(AuditLog::query()
            ->where('action', AuditLogAction::ServicePointDeleted->value)
            ->where('entity_type', 'service_point')
            ->where('entity_id', $servicePoint->id)
            ->exists())->toBeTrue();
});

test('cross branch and unauthorized service point deletion attempts do not mutate', function () {
    [$owner, $organization, , $branch] = createServicePointDeletionContext();
    [, , , $otherBranch] = createServicePointDeletionContext();
    $foreignServicePoint = ServicePoint::factory()->for($otherBranch)->blocked()->create();
    $ownServicePoint = ServicePoint::factory()->for($branch)->blocked()->create();
    $unauthorizedUser = User::factory()->create();
    OrganizationUser::factory()
        ->forOrganization($organization)
        ->forUser($unauthorizedUser)
        ->forRole(roleForServicePointDeletion(SystemRole::Waiter))
        ->active()
        ->create();

    expect(fn () => app(DeleteServicePointAction::class)->handle($owner, $branch, $foreignServicePoint))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(DeleteServicePointAction::class)->handle($unauthorizedUser, $branch, $ownServicePoint))
        ->toThrow(AuthorizationException::class);

    expect($foreignServicePoint->fresh())->not->toBeNull()
        ->and($ownServicePoint->fresh())->not->toBeNull();
});

test('repeated delete confirmation cannot delete a different service point', function () {
    [$owner, $organization, $brand, $branch] = createServicePointDeletionContext();
    $selected = ServicePoint::factory()->for($branch)->blocked()->create(['name' => 'Selected deletion row']);
    $other = ServicePoint::factory()->for($branch)->blocked()->create(['name' => 'Other protected row']);

    Livewire::actingAs($owner)
        ->test(ServicePointsIndex::class, compact('organization', 'brand', 'branch'))
        ->call('deleteServicePoint', $selected->id)
        ->assertHasNoErrors()
        ->assertDontSee('Selected deletion row')
        ->assertSee('Other protected row');

    expect(fn () => app(DeleteServicePointAction::class)->handle($owner, $branch, $selected))
        ->toThrow(ModelNotFoundException::class);

    expect($other->fresh())->not->toBeNull();
});

test('delete control is shown to service point managers', function () {
    [$owner, $organization, $brand, $branch] = createServicePointDeletionContext();
    ServicePoint::factory()->for($branch)->blocked()->create(['name' => 'Control table']);

    Livewire::actingAs($owner)
        ->test(ServicePointsIndex::class, compact('organization', 'brand', 'branch'))
        ->assertSet('canManageServicePoints', true)
        ->assertSeeHtml('wire:click="deleteServicePoint(');
});

test('delete control is hidden from users without service point management permission', function () {
    [, $organization, $brand, $branch] = createServicePointDeletionContext();
    ServicePoint::factory()->for($branch)->blocked()->create(['name' => 'Protected deletion table']);
    $waiter = User::factory()->create();
    OrganizationUser::factory()
        ->forOrganization($organization)
        ->forUser($waiter)
        ->forRole(roleForServicePointDeletion(SystemRole::Waiter))
        ->active()
        ->create();

    Livewire::actingAs($waiter)
        ->test(ServicePointsIndex::class, compact('organization', 'brand', 'branch'))
        ->assertSet('canManageServicePoints', false)
        ->assertDontSeeHtml('wire:click="deleteServicePoint(');
});

test('owned demo service point can be restored by the CRUD seeder', function () {
    Storage::fake('public');
    $this->seed(DemoRestaurantSeeder::class);

    $owner = User::query()->where('email', 'owner@demo.test')->firstOrFail();
    $servicePoint = ServicePoint::query()
        ->where('internal_code', DemoOrganizationCrudSeeder::INACTIVE_SERVICE_POINT_CODE)
        ->firstOrFail();
    $branch = Branch::query()->whereKey($servicePoint->branch_id)->firstOrFail();

    app(DeleteServicePointAction::class)->handle($owner, $branch, $servicePoint);
    expect(ServicePoint::query()->whereKey($servicePoint->id)->exists())->toBeFalse();

    $this->seed(DemoOrganizationCrudSeeder::class);

    $restored = ServicePoint::query()
        ->where('branch_id', $branch->id)
        ->where('internal_code', DemoOrganizationCrudSeeder::INACTIVE_SERVICE_POINT_CODE)
        ->firstOrFail();

    expect($restored->id)->toBe($servicePoint->id)
        ->and($restored->is_active)->toBeFalse();
});

test('service point manager is authorized to restore an archived service point', function () {
    [$owner, , , $branch] = createServicePointDeletionContext();
    $servicePoint = ServicePoint::factory()->for($branch)->blocked()->create();
    $servicePoint->deleteOrFail();

    expect(Gate::forUser($owner)->allows('restore', $servicePoint))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $servicePoint))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $servicePoint))->toBeFalse();
});

test('service point manager can view and restore an archived service point without a page reload', function () {
    [$owner, $organization, $brand, $branch] = createServicePointDeletionContext();
    $servicePoint = ServicePoint::factory()->for($branch)->blocked()->create([
        'name' => 'Archived Service Point',
    ]);
    $servicePoint->deleteOrFail();

    Livewire::actingAs($owner)
        ->test(ServicePointsIndex::class, compact('organization', 'brand', 'branch'))
        ->assertDontSee('Archived Service Point')
        ->set('filterLifecycle', 'archived')
        ->assertSee('Archived Service Point')
        ->call('restoreServicePoint', $servicePoint->id)
        ->assertHasNoErrors();

    expect($servicePoint->fresh())->not->toBeNull();
});

test('livewire payload cannot restore a service point from another branch', function () {
    [$owner, $organization, $brand, $branch] = createServicePointDeletionContext();
    [, , , $foreignBranch] = createServicePointDeletionContext();
    $foreignServicePoint = ServicePoint::factory()->for($foreignBranch)->archived()->create();

    $caughtException = null;

    try {
        Livewire::actingAs($owner)
            ->test(ServicePointsIndex::class, compact('organization', 'brand', 'branch'))
            ->call('restoreServicePoint', $foreignServicePoint->id);
    } catch (Throwable $exception) {
        $caughtException = $exception;
    }

    expect($caughtException)->toBeInstanceOf(ModelNotFoundException::class)
        ->and(ServicePoint::withTrashed()->findOrFail($foreignServicePoint->id)->trashed())->toBeTrue();
});

function createServicePointDeletionContext(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, [
        'name' => fake()->unique()->company().' Service Points',
    ]);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();

    return [$owner->fresh(), $organization, $brand, $branch];
}

function roleForServicePointDeletion(SystemRole $role): Role
{
    return Role::query()->where('code', $role->value)->firstOrFail();
}
