<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\TableSessions\TransferTableSessionAction;
use App\Enums\AuditLogAction;
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
use App\Models\User;
use App\Services\PublicQr\ActiveGuestAccessService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('waiter can transfer active session to a free service point without changing qr codes or orders', function () {
    [$organization, $oldServicePoint, $newServicePoint, $tableSession, $guest, $oldQrCode, $newQrCode, $waiter] = createPrompt117TransferContext();
    attachPrompt117Staff($waiter, $organization, [SystemPermission::ViewOrders]);

    $oldQrToken = $oldQrCode->public_token;
    $newQrToken = $newQrCode->public_token;

    $transferredSession = app(TransferTableSessionAction::class)->handle($tableSession, $newServicePoint, $waiter);

    expect($transferredSession->service_point_id)->toBe($newServicePoint->id)
        ->and($transferredSession->active_service_point_id)->toBe($newServicePoint->id)
        ->and($transferredSession->status)->toBe(TableSessionStatus::Active)
        ->and($oldServicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
        ->and($newServicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($oldQrCode->fresh()->public_token)->toBe($oldQrToken)
        ->and($oldQrCode->fresh()->service_point_id)->toBe($oldServicePoint->id)
        ->and($newQrCode->fresh()->public_token)->toBe($newQrToken)
        ->and($newQrCode->fresh()->service_point_id)->toBe($newServicePoint->id)
        ->and($guest->fresh()->table_session_id)->toBe($tableSession->id);

    $auditLog = AuditLog::query()
        ->where('action', AuditLogAction::TableSessionTransferred->value)
        ->where('entity_type', 'table_session')
        ->where('entity_id', $tableSession->id)
        ->latest('id')
        ->firstOrFail();

    expect($auditLog->user_id)->toBe($waiter->id)
        ->and($auditLog->branch_id)->toBe($tableSession->branch_id)
        ->and($auditLog->old_values['service_point_id'])->toBe($oldServicePoint->id)
        ->and($auditLog->new_values['service_point_id'])->toBe($newServicePoint->id);
});

test('waiter table detail exposes free service points and can transfer the session', function () {
    [$organization, $oldServicePoint, $newServicePoint, $tableSession, , , , $waiter] = createPrompt117TransferContext();
    attachPrompt117Staff($waiter, $organization, [SystemPermission::ViewOrders]);

    Livewire::actingAs($waiter)
        ->test(Overview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('overview.transfer.can_transfer', true)
        ->assertSee(__('ui.waiter.table_detail.perenesti_stol'))
        ->assertSee($newServicePoint->name)
        ->set('transferTargetServicePointId', $newServicePoint->id)
        ->call('transferTableSession')
        ->assertHasNoErrors()
        ->assertSee(__('ui.livewire.waiter.tabledetail.stol_perenesen_gosti_vidiat_novoe_mesto_qr_k'));

    expect($tableSession->fresh()->service_point_id)->toBe($newServicePoint->id)
        ->and($oldServicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
        ->and($newServicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

test('transfer rejects occupied target and keeps current session location', function () {
    [$organization, $oldServicePoint, $newServicePoint, $tableSession, , , , $waiter] = createPrompt117TransferContext();
    attachPrompt117Staff($waiter, $organization, [SystemPermission::ViewOrders]);
    $newServicePoint->forceFill(['status' => ServicePointStatus::Occupied])->save();

    expect(fn () => app(TransferTableSessionAction::class)->handle($tableSession, $newServicePoint, $waiter))
        ->toThrow(ValidationException::class);

    expect($tableSession->fresh()->service_point_id)->toBe($oldServicePoint->id)
        ->and($oldServicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($newServicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

test('guest restored from original qr sees the current transferred service point', function () {
    [$organization, $oldServicePoint, $newServicePoint, $tableSession, $guest, $oldQrCode, , $waiter] = createPrompt117TransferContext();
    attachPrompt117Staff($waiter, $organization, [SystemPermission::ViewOrders]);

    app(TransferTableSessionAction::class)->handle($tableSession, $newServicePoint, $waiter);

    Livewire::withCookie(prompt117GuestTokenCookieName($oldQrCode), $guest->guest_token)
        ->test(GuestEntry::class, ['token' => $oldQrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $guest->id)
        ->assertSet('landing.service_point_name', $newServicePoint->name)
        ->assertSeeText($newServicePoint->name)
        ->assertDontSeeText($oldServicePoint->name);

    request()->cookies->set(prompt117GuestTokenCookieName($oldQrCode), $guest->guest_token);

    expect(app(ActiveGuestAccessService::class)->findAuthorizedGuest(
        $oldQrCode->public_token,
        $tableSession->id,
        $guest->id,
    )?->id)->toBe($guest->id);
});

function createPrompt117TransferContext(): array
{
    $waiter = User::factory()->create(['name' => 'Prompt 117 Waiter']);
    $organization = (new CreateOrganizationAction)->handle($waiter, ['name' => 'Prompt 117 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 117 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 117 Branch',
            'currency' => 'EUR',
        ]);
    $oldServicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Стол 4',
            'display_number' => '4',
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    $newServicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Стол 9',
            'display_number' => '9',
            'status' => ServicePointStatus::Free,
            'is_active' => true,
        ]);
    $oldQrCode = QrCode::factory()
        ->for($oldServicePoint)
        ->create([
            'public_token' => 'prompt117oldstabletoken',
            'short_code' => 'QR-P117-4',
            'status' => QrCodeStatus::Active,
        ]);
    $newQrCode = QrCode::factory()
        ->for($newServicePoint)
        ->create([
            'public_token' => 'prompt117newstabletoken',
            'short_code' => 'QR-P117-9',
            'status' => QrCodeStatus::Active,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($oldServicePoint)
        ->active()
        ->waiterOpened()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    return [$organization, $oldServicePoint, $newServicePoint, $tableSession, $guest, $oldQrCode, $newQrCode, $waiter->fresh()];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt117Staff(User $user, Organization $organization, array $permissions): Role
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

function prompt117GuestTokenCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
