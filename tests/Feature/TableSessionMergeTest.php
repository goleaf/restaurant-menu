<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\TableSessions\CloseTableSessionAction;
use App\Actions\TableSessions\MergeTableSessionServicePointAction;
use App\Enums\AuditLogAction;
use App\Enums\GuestTableEntryState;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\Waiter\TableDetail\Overview;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('waiter can merge a free service point into an active session without changing qr codes', function () {
    [$organization, $mainServicePoint, $linkedServicePoint, $tableSession, $guest, $mainQrCode, $linkedQrCode, $waiter] = createPrompt118MergeContext();
    attachPrompt118Staff($waiter, $organization, [SystemPermission::ViewOrders]);

    $mainToken = $mainQrCode->public_token;
    $linkedToken = $linkedQrCode->public_token;

    $mergedSession = app(MergeTableSessionServicePointAction::class)->handle($tableSession, $linkedServicePoint, $waiter);

    expect($mergedSession->id)->toBe($tableSession->id)
        ->and($mergedSession->service_point_id)->toBe($mainServicePoint->id)
        ->and($mergedSession->active_service_point_id)->toBe($mainServicePoint->id)
        ->and($mainServicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($linkedServicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($mainQrCode->fresh()->public_token)->toBe($mainToken)
        ->and($mainQrCode->fresh()->service_point_id)->toBe($mainServicePoint->id)
        ->and($linkedQrCode->fresh()->public_token)->toBe($linkedToken)
        ->and($linkedQrCode->fresh()->service_point_id)->toBe($linkedServicePoint->id)
        ->and($guest->fresh()->table_session_id)->toBe($tableSession->id);

    $link = TableSessionServicePoint::query()
        ->where('table_session_id', $tableSession->id)
        ->where('service_point_id', $linkedServicePoint->id)
        ->firstOrFail();

    expect($link->linked_by_user_id)->toBe($waiter->id)
        ->and($link->unlinked_at)->toBeNull();

    $auditLog = AuditLog::query()
        ->where('action', AuditLogAction::TableSessionServicePointLinked->value)
        ->where('entity_type', 'table_session')
        ->where('entity_id', $tableSession->id)
        ->latest('id')
        ->firstOrFail();

    expect($auditLog->user_id)->toBe($waiter->id)
        ->and($auditLog->branch_id)->toBe($tableSession->branch_id)
        ->and($auditLog->new_values['linked_service_point_id'])->toBe($linkedServicePoint->id);
});

test('merge rejects occupied service point and keeps qr codes unchanged', function () {
    [$organization, $mainServicePoint, $linkedServicePoint, $tableSession, , $mainQrCode, $linkedQrCode, $waiter] = createPrompt118MergeContext();
    attachPrompt118Staff($waiter, $organization, [SystemPermission::ViewOrders]);
    $linkedServicePoint->forceFill(['status' => ServicePointStatus::Occupied])->save();

    expect(fn () => app(MergeTableSessionServicePointAction::class)->handle($tableSession, $linkedServicePoint, $waiter))
        ->toThrow(ValidationException::class);

    expect(TableSessionServicePoint::query()->where('table_session_id', $tableSession->id)->exists())->toBeFalse()
        ->and($mainServicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($linkedServicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($mainQrCode->fresh()->service_point_id)->toBe($mainServicePoint->id)
        ->and($linkedQrCode->fresh()->service_point_id)->toBe($linkedServicePoint->id);
});

test('waiter table detail can merge another service point and shows linked places', function () {
    [$organization, , $linkedServicePoint, $tableSession, , , , $waiter] = createPrompt118MergeContext();
    attachPrompt118Staff($waiter, $organization, [SystemPermission::ViewOrders]);

    Livewire::actingAs($waiter)
        ->test(Overview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('overview.merge.can_merge', true)
        ->assertSee(__('ui.waiter.table_detail.obieedinit_stoly'))
        ->assertSee($linkedServicePoint->name)
        ->set('mergeTargetServicePointId', $linkedServicePoint->id)
        ->call('mergeServicePoint')
        ->assertHasNoErrors()
        ->assertSee(__('ui.livewire.waiter.tabledetail.stoly_obieedineny_qr_kody_kazdogo_fiziceskog'))
        ->assertSee($linkedServicePoint->name);

    expect(TableSessionServicePoint::query()
        ->where('table_session_id', $tableSession->id)
        ->where('service_point_id', $linkedServicePoint->id)
        ->whereNull('unlinked_at')
        ->exists())->toBeTrue();
});

test('guest scanning linked qr creates a join request for the main active session', function () {
    [$organization, , $linkedServicePoint, $tableSession, , , $linkedQrCode, $waiter] = createPrompt118MergeContext();
    attachPrompt118Staff($waiter, $organization, [SystemPermission::ViewOrders]);
    app(MergeTableSessionServicePointAction::class)->handle($tableSession, $linkedServicePoint, $waiter);

    Livewire::test(GuestEntry::class, ['token' => $linkedQrCode->public_token])
        ->set('guestName', 'Bella')
        ->call('enterTable')
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('entryState', GuestTableEntryState::JoinRequestCreated->value)
        ->assertSet('landing.service_point_name', $tableSession->servicePoint->name);

    $joinRequest = TableSessionJoinRequest::query()
        ->where('table_session_id', $tableSession->id)
        ->where('guest_name', 'Bella')
        ->firstOrFail();

    expect($joinRequest->guest_token)->toHaveLength(64);
});

test('closing a merged session frees every linked service point without changing qr codes', function () {
    [$organization, $mainServicePoint, $linkedServicePoint, $tableSession, , $mainQrCode, $linkedQrCode, $waiter] = createPrompt118MergeContext();
    attachPrompt118Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::CloseTableSessions]);
    app(MergeTableSessionServicePointAction::class)->handle($tableSession, $linkedServicePoint, $waiter);

    app(CloseTableSessionAction::class)->handle($tableSession, $waiter);

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed)
        ->and($mainServicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
        ->and($linkedServicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
        ->and($mainQrCode->fresh()->service_point_id)->toBe($mainServicePoint->id)
        ->and($linkedQrCode->fresh()->service_point_id)->toBe($linkedServicePoint->id)
        ->and(TableSessionServicePoint::query()
            ->where('table_session_id', $tableSession->id)
            ->where('service_point_id', $linkedServicePoint->id)
            ->whereNotNull('unlinked_at')
            ->exists())->toBeTrue();
});

function createPrompt118MergeContext(): array
{
    $waiter = User::factory()->create(['name' => 'Prompt 118 Waiter']);
    $organization = (new CreateOrganizationAction)->handle($waiter, ['name' => 'Prompt 118 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 118 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 118 Branch',
            'currency' => 'EUR',
        ]);
    $mainServicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Стол 5',
            'display_number' => '5',
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    $linkedServicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Стол 6',
            'display_number' => '6',
            'status' => ServicePointStatus::Free,
            'is_active' => true,
        ]);
    $mainQrCode = QrCode::factory()
        ->for($mainServicePoint)
        ->create([
            'public_token' => 'prompt118mainstabletoken',
            'short_code' => 'QR-P118-5',
            'status' => QrCodeStatus::Active,
        ]);
    $linkedQrCode = QrCode::factory()
        ->for($linkedServicePoint)
        ->create([
            'public_token' => 'prompt118linkedstabletoken',
            'short_code' => 'QR-P118-6',
            'status' => QrCodeStatus::Active,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($mainServicePoint)
        ->active()
        ->waiterOpened()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    return [$organization, $mainServicePoint, $linkedServicePoint, $tableSession, $guest, $mainQrCode, $linkedQrCode, $waiter->fresh()];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt118Staff(User $user, Organization $organization, array $permissions): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    foreach ($permissions as $permission) {
        $permissionModel = Permission::query()
            ->where('code', $permission->value)
            ->firstOrFail();

        $role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
    }

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $role;
}
