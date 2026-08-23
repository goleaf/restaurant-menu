<?php

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\Notifications\MarkGuestNotificationsReadAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Notifications\UnreadCount;
use App\Livewire\PublicQr\Notifications as GuestNotifications;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use App\Notifications\BillRequestedNotification;
use App\Notifications\KitchenItemReadyNotification;
use App\Notifications\WaiterCalledNotification;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('new join request creates unread database notifications for active guests', function () {
    [, , , $tableSession] = createPrompt81NotificationContext();
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $boris = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Boris',
            'status' => TableSessionGuestStatus::Active,
        ]);

    $joinRequest = app(CreateTableSessionJoinRequestAction::class)->handle($tableSession, '  Mira  ');

    expect($joinRequest)->not->toBeNull()
        ->and($ana->unreadNotifications()->where('type', 'join_request_created')->count())->toBe(1)
        ->and($boris->unreadNotifications()->where('type', 'join_request_created')->count())->toBe(1)
        ->and(data_get($ana->unreadNotifications()->firstOrFail()->data, 'guest_name'))->toBe('Mira')
        ->and((int) data_get($ana->unreadNotifications()->firstOrFail()->data, 'join_request_id'))->toBe($joinRequest->id);

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $ana->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.livewire.publicqr.notifications.novyi_gost_zdet_podtverzdeniia_7813e12a'))
        ->assertSee('Mira');
});

test('guest notification read action marks only an allowed unread notification', function (): void {
    [, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);
    $guest->notify(new KitchenItemReadyNotification($ticketItem));
    $notification = $guest->unreadNotifications()
        ->where('type', 'kitchen_item_ready')
        ->firstOrFail();
    $action = app(MarkGuestNotificationsReadAction::class);

    expect($action->one($guest, $notification->id, ['draft_order_confirmed']))->toBeFalse()
        ->and($action->one($guest, $notification->id, ['kitchen_item_ready']))->toBeTrue()
        ->and($action->one($guest, $notification->id, ['kitchen_item_ready']))->toBeFalse()
        ->and($notification->fresh()->read_at)->not->toBeNull();
});

test('guest notification ui can mark one notification as read', function (): void {
    [, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);
    $guest->notify(new KitchenItemReadyNotification($ticketItem));
    $notification = $guest->unreadNotifications()
        ->where('type', 'kitchen_item_ready')
        ->firstOrFail();

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->call('markNotificationRead', $notification->id)
        ->assertSet('unreadCount', 0)
        ->assertSet('notifications', []);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('sent draft creates unread database notification for waiter and unread count polls it', function () {
    [$organization, , , $tableSession, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create(['name' => 'Prompt 81 Waiter']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $draftOrder = createPrompt81DraftOrder($tableSession, $guest);

    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    $notification = $waiter->unreadNotifications()
        ->where('type', 'draft_order_sent_to_waiter')
        ->firstOrFail();

    expect((int) data_get($notification->data, 'draft_order_id'))->toBe($draftOrder->id)
        ->and(data_get($notification->data, 'sent_by_guest_name'))->toBe('Ana');

    Livewire::actingAs($waiter)
        ->test(UnreadCount::class)
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.notifications.unread_count.notifications'))
        ->assertSee(__('ui.livewire.notifications.unreadcount.novyi_zakaz'))
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);

    expect($waiter->unreadNotifications()->count())->toBe(0);
});

test('staff notification ui lists waiter events and can mark one notification read', function () {
    [, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create(['name' => 'Prompt 82 Panel Waiter']);
    $waiterCall = WaiterCall::factory()
        ->forServicePoint($servicePoint)
        ->forTableSession($tableSession)
        ->create(['requested_by_guest_id' => $guest->id]);
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);

    $waiter->notify(new WaiterCalledNotification($waiterCall));
    $waiter->notify(new BillRequestedNotification($tableSession, $guest));
    $waiter->notify(new KitchenItemReadyNotification($ticketItem));

    $waiterCallNotificationId = $waiter->unreadNotifications()
        ->where('type', 'waiter_called')
        ->firstOrFail()
        ->id;

    Livewire::actingAs($waiter)
        ->test(UnreadCount::class)
        ->assertSet('unreadCount', 3)
        ->assertSee(__('ui.livewire.notifications.unreadcount.vyzov_oficianta'))
        ->assertSee(__('ui.livewire.notifications.unreadcount.prosba_sceta'))
        ->assertSee(__('ui.livewire.notifications.unreadcount.poziciia_gotova_d55866f3'))
        ->call('markNotificationRead', $waiterCallNotificationId)
        ->assertSet('unreadCount', 2);

    expect($waiter->unreadNotifications()->where('type', 'waiter_called')->count())->toBe(0)
        ->and($waiter->readNotifications()->where('type', 'waiter_called')->count())->toBe(1);
});

test('kitchen ready creates one unread database notification for waiter recipients', function () {
    [$organization, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create(['name' => 'Prompt 81 Ready Waiter']);
    $cook = User::factory()->create(['name' => 'Prompt 81 Cook']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    attachPrompt81Staff($cook, $organization, SystemRole::Cook);
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::Ready,
        user: $cook,
        departmentTypes: [],
        roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    expect($waiter->unreadNotifications()->where('type', 'kitchen_item_ready')->count())->toBe(1)
        ->and($guest->unreadNotifications()->where('type', 'kitchen_item_ready')->count())->toBe(1)
        ->and(data_get($waiter->unreadNotifications()->firstOrFail()->data, 'item_name'))->toBe('Prompt 81 Soup');

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::Ready,
        user: $cook,
        departmentTypes: [],
        roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    expect($waiter->unreadNotifications()->where('type', 'kitchen_item_ready')->count())->toBe(1)
        ->and($guest->unreadNotifications()->where('type', 'kitchen_item_ready')->count())->toBe(1);
});

test('kitchen in progress creates guest notification and guest notification ui shows cooking and ready states', function () {
    [$organization, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $cook = User::factory()->create(['name' => 'Prompt 82 Cook']);
    attachPrompt81Staff($cook, $organization, SystemRole::Cook);
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::InProgress,
        user: $cook,
        departmentTypes: [],
        roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    expect($guest->unreadNotifications()->where('type', 'kitchen_item_cooking')->count())->toBe(1);

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.livewire.publicqr.notifications.poziciia_gotovitsia_c07e0e57'))
        ->assertSee('Prompt 81 Soup');

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::Ready,
        user: $cook,
        departmentTypes: [],
        roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 2)
        ->assertSee(__('ui.livewire.notifications.unreadcount.poziciia_gotova_d55866f3'))
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);
});

test('rejected draft creates unread database notifications for active guests', function () {
    [$organization, , , $tableSession, $guest] = createPrompt81NotificationContext();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $waiter = User::factory()->create(['name' => 'Prompt 81 Reject Waiter']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);
    $draftOrder = createPrompt81DraftOrder($tableSession, $guest);
    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    app(RejectDraftOrderByWaiterAction::class)->handle($draftOrder->fresh(), $waiter, 'Please remove the soup.');

    expect($guest->unreadNotifications()->where('type', 'draft_order_rejected')->count())->toBe(1)
        ->and($zara->unreadNotifications()->where('type', 'draft_order_rejected')->count())->toBe(1)
        ->and(data_get($guest->unreadNotifications()->where('type', 'draft_order_rejected')->firstOrFail()->data, 'rejection_reason'))->toBe('Please remove the soup.');

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.livewire.publicqr.notifications.zakaz_otklonen'))
        ->assertSee('Please remove the soup.');
});

test('confirmed draft creates unread database notifications for active guests', function () {
    [$organization, , , $tableSession, $guest] = createPrompt81NotificationContext();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $waiter = User::factory()->create(['name' => 'Prompt 82 Confirm Waiter']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);
    $draftOrder = createPrompt81DraftOrder($tableSession, $guest);
    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder->fresh(), $waiter);

    expect($order->draft_order_id)->toBe($draftOrder->id)
        ->and($guest->unreadNotifications()->where('type', 'draft_order_confirmed')->count())->toBe(1)
        ->and($zara->unreadNotifications()->where('type', 'draft_order_confirmed')->count())->toBe(1);

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.livewire.publicqr.notifications.zakaz_podtverzden'))
        ->assertSee(__('ui.livewire.publicqr.notifications.oficiant_podtverdil_zakaz'));
});

function createPrompt81NotificationContext(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 81 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 81 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 81 Branch',
            'currency' => 'EUR',
        ]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 81 Table',
            'status' => ServicePointStatus::Occupied,
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

    return [$organization, $branch, $servicePoint, $tableSession, $guest];
}

function createPrompt81DraftOrder(TableSession $tableSession, TableSessionGuest $guest): DraftOrder
{
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Prompt 81 Soup',
            'quantity' => 1,
            'unit_price_cents' => 850,
            'modifier_total_cents' => 0,
            'total_price_cents' => 850,
            'selected_modifiers' => [],
        ]);

    return $draftOrder;
}

function createPrompt81KitchenTicketItem(
    Branch $branch,
    ServicePoint $servicePoint,
    TableSession $tableSession,
    TableSessionGuest $guest,
): KitchenTicketItem {
    $department = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Prompt 81 Kitchen',
            'is_active' => true,
        ]);
    $order = Order::factory()
        ->for($branch)
        ->for($servicePoint)
        ->for($tableSession)
        ->create([
            'status' => OrderStatus::SentToKitchenBar,
            'total_price_cents' => 850,
            'currency' => 'EUR',
        ]);
    $orderItem = OrderItem::factory()
        ->for($order)
        ->for($guest, 'guest')
        ->create([
            'item_name' => 'Prompt 81 Soup',
            'guest_name' => 'Ana',
            'quantity' => 1,
            'unit_price_cents' => 850,
            'modifier_total_cents' => 0,
            'total_price_cents' => 850,
            'selected_modifiers' => [],
        ]);
    $ticket = KitchenTicket::factory()
        ->for($order)
        ->create([
            'branch_id' => $branch->id,
            'service_point_id' => $servicePoint->id,
            'table_session_id' => $tableSession->id,
            'kitchen_department_id' => $department->id,
            'department_type' => KitchenDepartmentType::Kitchen->value,
            'department_name' => $department->name,
            'status' => KitchenTicketStatus::Sent,
        ]);

    return KitchenTicketItem::factory()
        ->for($ticket, 'kitchenTicket')
        ->for($orderItem, 'orderItem')
        ->create([
            'table_session_guest_id' => $guest->id,
            'menu_item_id' => null,
            'guest_name' => 'Ana',
            'item_name' => 'Prompt 81 Soup',
            'quantity' => 1,
            'status' => KitchenTicketItemStatus::New,
            'selected_modifiers' => [],
            'comment' => null,
        ]);
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt81Staff(User $user, Organization $organization, SystemRole $roleCode, array $permissions = []): Role
{
    $role = Role::query()
        ->where('code', $roleCode->value)
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

function prompt82GuestCookieName(string $publicToken): string
{
    return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
}
