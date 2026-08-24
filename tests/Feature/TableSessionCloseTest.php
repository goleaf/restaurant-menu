<?php

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\Waiter\TableDetail\Payment;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('staff with close table sessions permission can close an active session and open a new one without changing qr or orders', function () {
    [$organization, $branch, $servicePoint, $tableSession, $guest, $qrCode, $menuItem] = createPrompt68CloseContext();
    $staff = User::factory()->create(['name' => 'Session Closer']);
    attachPrompt68Staff($staff, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::CloseTableSessions,
    ]);

    $orderCountBefore = Order::query()
        ->where('table_session_id', $tableSession->id)
        ->count();
    $orderItemCountBefore = OrderItem::query()
        ->whereHas('order', fn ($query) => $query->where('table_session_id', $tableSession->id))
        ->count();

    Livewire::actingAs($staff)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.session.can_close', true)
        ->assertSet('payment.session.close_requires_warning', true)
        ->assertSee('id="dangerous-action-close-table-session-confirmation"', false)
        ->assertSee('name="closeTableConfirmation"', false)
        ->set('closeTableConfirmation', 'CLOSE')
        ->call('closeTableSession')
        ->assertSee(__('payments.messages.session_closed'));

    $closedSession = $tableSession->fresh();

    expect($closedSession->status)->toBe(TableSessionStatus::Closed)
        ->and($closedSession->active_service_point_id)->toBeNull()
        ->and($closedSession->pending_service_point_id)->toBeNull()
        ->and($closedSession->closed_by_user_id)->toBe($staff->id)
        ->and($closedSession->ended_at)->not->toBeNull()
        ->and((int) data_get($closedSession->metadata, 'manually_closed_by_user_id'))->toBe($staff->id)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
        ->and($qrCode->fresh()->public_token)->toBe($qrCode->public_token)
        ->and($qrCode->fresh()->status)->toBe(QrCodeStatus::Active)
        ->and($qrCode->fresh()->service_point_id)->toBe($servicePoint->id)
        ->and(Order::query()->where('table_session_id', $tableSession->id)->count())->toBe($orderCountBefore)
        ->and(Order::query()->where('table_session_id', $tableSession->id)->firstOrFail()->status)->toBe(OrderStatus::Closed)
        ->and(OrderItem::query()->whereHas('order', fn ($query) => $query->where('table_session_id', $tableSession->id))->count())->toBe($orderItemCountBefore);

    expect(fn () => app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $closedSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
    ))->toThrow(ValidationException::class);

    $newSession = app(OpenTableSessionForServicePointAction::class)->handle($servicePoint->fresh(), $staff);

    expect($newSession->id)->not->toBe($tableSession->id)
        ->and($newSession->branch_id)->toBe($branch->id)
        ->and($newSession->service_point_id)->toBe($servicePoint->id)
        ->and($newSession->status)->toBe(TableSessionStatus::Active)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($qrCode->fresh()->public_token)->toBe($qrCode->public_token)
        ->and($qrCode->fresh()->status)->toBe(QrCodeStatus::Active);

    Livewire::withCookie(prompt68GuestCookieName($qrCode), $guest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', null)
        ->assertSet('currentGuestId', null)
        ->assertSet('entryState', '')
        ->assertSet('guestCanViewTable', false)
        ->assertSet('guestCanAddItems', false);
});

test('staff without close table sessions permission cannot manually close an unpaid active session', function () {
    [$organization, , $servicePoint, $tableSession] = createPrompt68CloseContext();
    $waiter = User::factory()->create(['name' => 'Viewer Waiter']);
    attachPrompt68Staff($waiter, $organization, [SystemPermission::ViewOrders], SystemRole::Cook);

    Livewire::actingAs($waiter)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.session.can_close', false)
        ->call('closeTableSession')
        ->assertHasErrors(['table_session']);

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Active)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

test('browser tampering cannot bypass the unpaid session close confirmation', function () {
    [$organization, , $servicePoint, $tableSession] = createPrompt68CloseContext();
    $staff = User::factory()->create(['name' => 'Tamper resistant closer']);
    attachPrompt68Staff($staff, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::CloseTableSessions,
    ]);

    Livewire::actingAs($staff)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.session.close_requires_warning', true)
        ->set('payment.session.close_requires_warning', false)
        ->call('closeTableSession')
        ->assertHasErrors(['closeTableConfirmation']);

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Active)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

function createPrompt68CloseContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 68 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 68 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 68 Branch',
            'currency' => 'EUR',
        ]);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 68 Table',
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt68stabletoken',
            'short_code' => 'P68-CLOSE',
            'status' => QrCodeStatus::Active,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::ConvertedToOrder]);
    $order = Order::factory()
        ->for($branch)
        ->for($servicePoint)
        ->for($tableSession)
        ->for($draftOrder, 'draftOrder')
        ->create([
            'status' => OrderStatus::Served,
            'total_price_cents' => 1200,
            'currency' => 'EUR',
        ]);
    OrderItem::factory()
        ->for($order)
        ->for($guest, 'guest')
        ->create([
            'guest_name' => $guest->guest_name,
            'item_name' => 'Saved dinner',
            'unit_price_cents' => 1200,
            'total_price_cents' => 1200,
        ]);

    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 68 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['is_active' => true]);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Future tea',
            'price_cents' => 300,
            'is_available' => true,
        ]);

    return [$organization, $branch, $servicePoint, $tableSession, $guest, $qrCode, $menuItem];
}

function attachPrompt68Staff(
    User $user,
    Organization $organization,
    array $permissions,
    SystemRole $systemRole = SystemRole::Waiter,
): Role {
    $role = Role::query()
        ->where('code', $systemRole->value)
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

function prompt68GuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
