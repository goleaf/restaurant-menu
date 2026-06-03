<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as ServicePointsIndex;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('waiter open table action creates active table session and occupies service point', function () {
    [$organization, $brand, $branch, $manager] = createWaiterOpenTableBranch();
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Window table',
            'status' => ServicePointStatus::Free,
        ]);

    $tableSession = app(OpenTableSessionForServicePointAction::class)
        ->handle($servicePoint, $manager);

    expect($tableSession->branch_id)->toBe($branch->id);
    expect($tableSession->service_point_id)->toBe($servicePoint->id);
    expect($tableSession->active_service_point_id)->toBe($servicePoint->id);
    expect($tableSession->opened_by_user_id)->toBe($manager->id);
    expect($tableSession->status)->toBe(TableSessionStatus::Active);
    expect($tableSession->source)->toBe(TableSessionSource::WaiterOpened);
    expect($tableSession->started_at)->not->toBeNull();
    expect($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);

    expect($organization->id)->toBe($branch->organization_id);
    expect($brand->id)->toBe($branch->brand_id);
});

test('waiter open table action reuses existing active session', function () {
    [, , $branch, $manager] = createWaiterOpenTableBranch();
    $servicePoint = ServicePoint::factory()->for($branch)->create();

    $firstTableSession = app(OpenTableSessionForServicePointAction::class)
        ->handle($servicePoint, $manager);
    $secondTableSession = app(OpenTableSessionForServicePointAction::class)
        ->handle($servicePoint, $manager);

    expect($secondTableSession->id)->toBe($firstTableSession->id);
    expect(TableSession::query()
        ->where('service_point_id', $servicePoint->id)
        ->where('status', TableSessionStatus::Active->value)
        ->count())->toBe(1);
});

test('database prevents two active table sessions for one service point', function () {
    [, , $branch] = createWaiterOpenTableBranch();
    $servicePoint = ServicePoint::factory()->for($branch)->create();

    TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();

    expect(fn () => TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create())->toThrow(QueryException::class);
});

test('user with view orders can open a table from service point page', function () {
    [$organization, $brand, $branch, $manager] = createWaiterOpenTableBranch();
    grantWaiterOpenTablePermission($manager, $organization, SystemPermission::ViewOrders);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Service table',
            'status' => ServicePointStatus::Free,
        ]);

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee('Service points');

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canOpenTable', true)
        ->assertSee('Open table')
        ->call('openTable', $servicePoint->id)
        ->assertHasNoErrors()
        ->assertSee('Active session')
        ->assertSee('Table opened');

    expect(TableSession::query()
        ->where('service_point_id', $servicePoint->id)
        ->where('status', TableSessionStatus::Active->value)
        ->count())->toBe(1);
    expect($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

test('user with confirm orders can open a table from service point page', function () {
    [$organization, $brand, $branch, $manager] = createWaiterOpenTableBranch();
    grantWaiterOpenTablePermission($manager, $organization, SystemPermission::ConfirmOrders);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Confirm table']);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canOpenTable', true)
        ->call('openTable', $servicePoint->id)
        ->assertHasNoErrors();

    expect($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

test('waiter role without order permission cannot open a table', function () {
    [$organization, $brand, $branch] = createWaiterOpenTableBranch();
    $waiter = User::factory()->create();
    attachWaiterOpenTableWaiter($waiter, $organization);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'No order permission table']);

    Livewire::actingAs($waiter)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canOpenTable', false)
        ->assertDontSee('Open table')
        ->call('openTable', $servicePoint->id)
        ->assertForbidden();

    expect(TableSession::query()
        ->where('service_point_id', $servicePoint->id)
        ->where('status', TableSessionStatus::Active->value)
        ->exists())->toBeFalse();
});

function createWaiterOpenTableBranch(string $organizationName = 'Waiter Open Group', string $brandName = 'Waiter Open Brand'): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => $brandName.' Branch']);

    return [$organization, $brand, $branch, $manager->fresh()];
}

function grantWaiterOpenTablePermission(User $user, Organization $organization, SystemPermission $permission): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->firstOrFail();
    $permissionRecord = Permission::query()
        ->where('code', $permission->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permissionRecord->id, ['enabled' => true]);
}

function attachWaiterOpenTableWaiter(User $user, Organization $organization): void
{
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);
}
