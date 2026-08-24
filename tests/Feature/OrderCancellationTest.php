<?php

use App\Actions\Orders\CancelOrderItemAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Payments\BuildManualPaymentSummaryAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\AuditLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Livewire\Kitchen\Dashboard as KitchenDashboard;
use App\Livewire\PublicQr\OrderStatuses;
use App\Livewire\Waiter\TableDetail\OrderFulfilment;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
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

test('waiter cancels order with required reason and guests see cancellation', function () {
    [$organization, $tableSession, $order, $kitchenDepartment, $ticketItem, $guest, $qrCode] = createPrompt121SentOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 121 Waiter']);
    $chef = User::factory()->create(['name' => 'Prompt 121 Chef']);

    attachPrompt121Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::CancelOrders,
    ]);
    attachPrompt121Staff($chef, $organization, SystemRole::HeadChef);

    $ticketItem->forceFill(['status' => KitchenTicketItemStatus::Ready])->save();

    Livewire::actingAs($waiter)
        ->test(OrderFulfilment::class, ['tableSessionId' => $tableSession->id])
        ->assertSee('Cancel order')
        ->assertSee('Some positions are already ready or served.')
        ->set('orderCancellationReason', '   ')
        ->call('cancelOrder')
        ->assertHasErrors('orderCancellationReason')
        ->set('orderCancellationReason', 'Guest asked to cancel after a long wait.')
        ->call('cancelOrder')
        ->assertHasNoErrors()
        ->assertSee('Order cancelled.')
        ->assertSet('orderFulfilment.draft.order_status_value', OrderStatus::Cancelled->value)
        ->assertSet('orderFulfilment.draft.cancellation_reason', 'Guest asked to cancel after a long wait.');

    $order = $order->fresh();
    $statusLog = OrderStatusLog::query()
        ->where('order_id', $order->id)
        ->where('event', OrderStatusLogEvent::OrderCancelled->value)
        ->firstOrFail();
    $auditLog = AuditLog::query()
        ->where('entity_type', 'order')
        ->where('entity_id', $order->id)
        ->where('action', AuditLogAction::OrderCancelled->value)
        ->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->metadata['cancellation_reason'])->toBe('Guest asked to cancel after a long wait.')
        ->and($order->metadata['cancelled_by_user_id'])->toBe($waiter->id)
        ->and($order->metadata['ready_ticket_items_at_cancellation'])->toBe(1)
        ->and($statusLog->reason)->toBe('Guest asked to cancel after a long wait.')
        ->and($statusLog->metadata['ready_ticket_items_count'])->toBe(1)
        ->and($auditLog->new_values['reason'])->toBe('Guest asked to cancel after a long wait.');

    Livewire::withCookie(prompt121GuestCookieName($qrCode), $guest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 1,
        ])
        ->assertSet('serviceStatusValue', 'cancelled')
        ->assertSet('cancellationReason', 'Guest asked to cancel after a long wait.')
        ->assertSee(__('guest.statuses.service.cancelled_order'))
        ->assertSee('Guest asked to cancel after a long wait.');

    Livewire::actingAs($chef)
        ->test(KitchenDashboard::class)
        ->set('selectedDepartmentId', (string) $kitchenDepartment->id)
        ->call('refreshDepartment')
        ->assertDontSee('Prompt 121 Pizza');

    Livewire::actingAs($chef)
        ->test(KitchenDashboard::class)
        ->call('setItemStatus', $ticketItem->id, KitchenTicketItemStatus::InProgress->value)
        ->assertHasErrors('ticket_item_status');
});

test('director and shift manager can cancel an order without an explicit cancel permission', function (SystemRole $role) {
    [$organization, , $order] = createPrompt121SentOrderScenario();
    $staff = User::factory()->create(['name' => 'Prompt 121 '.$role->label()]);

    attachPrompt121Staff($staff, $organization, $role);

    app(ChangeOrderStatusAction::class)->handle(
        order: $order,
        newStatus: OrderStatus::Cancelled,
        changedBy: $staff,
        reason: 'Manager approved cancellation.',
    );

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
})->with([
    'director' => [SystemRole::Director],
    'shift manager' => [SystemRole::ShiftManager],
]);

test('waiter cancels one order item without deleting its history', function () {
    [$organization, $tableSession, $order, $kitchenDepartment, $ticketItem, $guest, $qrCode] = createPrompt121SentOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 121 Item Canceller']);
    $chef = User::factory()->create(['name' => 'Prompt 121 Item Cancellation Chef']);

    attachPrompt121Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::CancelOrders,
    ]);
    attachPrompt121Staff($chef, $organization, SystemRole::HeadChef);

    $remainingItem = OrderItem::factory()
        ->for($order)
        ->create([
            'table_session_guest_id' => $ticketItem->table_session_guest_id,
            'guest_name' => 'Ana',
            'guest_name_snapshot' => 'Ana',
            'item_name' => 'Prompt 121 Water',
            'item_name_snapshot' => 'Prompt 121 Water',
            'quantity' => 1,
            'unit_price_cents' => 500,
            'unit_price_snapshot_cents' => 500,
            'total_price_cents' => 500,
        ]);
    KitchenTicketItem::factory()
        ->for($ticketItem->kitchenTicket, 'kitchenTicket')
        ->for($remainingItem, 'orderItem')
        ->create([
            'table_session_guest_id' => $remainingItem->table_session_guest_id,
            'guest_name' => 'Ana',
            'item_name' => 'Prompt 121 Water',
            'quantity' => 1,
        ]);
    $order->forceFill(['total_price_cents' => 1700])->save();

    $cancelledItem = app(CancelOrderItemAction::class)->handle(
        orderItem: $ticketItem->orderItem,
        cancelledBy: $waiter,
        reason: 'Guest no longer wants this dish.',
    );

    $this->assertModelExists($cancelledItem);

    $summary = app(BuildManualPaymentSummaryAction::class)->handle($tableSession);
    $statusLog = OrderStatusLog::query()
        ->where('order_id', $order->id)
        ->where('event', OrderStatusLogEvent::OrderItemCancelled->value)
        ->firstOrFail();
    $auditLog = AuditLog::query()
        ->where('entity_type', 'order_item')
        ->where('entity_id', $cancelledItem->id)
        ->where('action', AuditLogAction::OrderItemVoided->value)
        ->firstOrFail();

    expect(OrderItem::query()->whereKey($cancelledItem->id)->exists())->toBeTrue()
        ->and(OrderItem::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and($cancelledItem->cancelled_at)->not->toBeNull()
        ->and($cancelledItem->cancelled_by_user_id)->toBe($waiter->id)
        ->and($cancelledItem->cancellation_reason)->toBe('Guest no longer wants this dish.')
        ->and($ticketItem->fresh()->status)->toBe(KitchenTicketItemStatus::Cancelled)
        ->and($order->fresh()->total_price_cents)->toBe(500)
        ->and($summary['confirmed_total'])->toBe('€5.00')
        ->and($statusLog->reason)->toBe('Guest no longer wants this dish.')
        ->and($statusLog->metadata['order_item_id'])->toBe($cancelledItem->id)
        ->and($auditLog->new_values['reason'])->toBe('Guest no longer wants this dish.');

    Livewire::withCookie(prompt121GuestCookieName($qrCode), $guest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 1,
        ])
        ->assertSet('itemStatuses.0.status_value', 'cancelled')
        ->assertSet('itemStatuses.1.status_value', 'accepted')
        ->assertSeeText('Prompt 121 Pizza')
        ->assertSeeText('Prompt 121 Water')
        ->assertSeeText(__('guest.statuses.items.cancelled'))
        ->assertDontSeeText('Guest no longer wants this dish.');

    Livewire::actingAs($chef)
        ->test(KitchenDashboard::class)
        ->set('selectedDepartmentId', (string) $kitchenDepartment->id)
        ->call('setItemStatus', $ticketItem->id, KitchenTicketItemStatus::InProgress->value)
        ->assertHasErrors('ticket_item_status');

    Livewire::actingAs($waiter)
        ->test(OrderFulfilment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('orderFulfilment.orders.0.items.0.is_cancelled', true)
        ->assertSet('orderFulfilment.orders.0.items.1.can_cancel', true)
        ->assertSeeText('Guest no longer wants this dish.')
        ->call('markTicketItemServed', $ticketItem->id)
        ->assertHasErrors('order_service')
        ->set('orderItemCancellationReason', 'x')
        ->call('cancelOrderItem', $remainingItem->id)
        ->assertHasErrors('orderItemCancellationReason')
        ->set('orderItemCancellationReason', 'Guest cancelled the remaining drink.')
        ->call('cancelOrderItem', $remainingItem->id)
        ->assertHasNoErrors()
        ->assertSet('orderFulfilment.orders.0.items.1.is_cancelled', true)
        ->assertSeeText(__('orders.items.messages.cancelled'));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(OrderItem::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and(OrderStatusLog::query()
            ->where('order_id', $order->id)
            ->where('event', OrderStatusLogEvent::OrderItemCancelled->value)
            ->count())->toBe(2);
});

test('repeated order item cancellation is rejected without duplicating history', function () {
    [$organization, , $order, , $ticketItem] = createPrompt121SentOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 121 Repeat Canceller']);

    attachPrompt121Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::CancelOrders,
    ]);

    app(CancelOrderItemAction::class)->handle(
        orderItem: $ticketItem->orderItem,
        cancelledBy: $waiter,
        reason: 'Guest cancelled once.',
    );

    expect(fn () => app(CancelOrderItemAction::class)->handle(
        orderItem: $ticketItem->orderItem,
        cancelledBy: $waiter,
        reason: 'Duplicate cancellation attempt.',
    ))->toThrow(BusinessRuleViolation::class);

    expect(OrderItem::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(OrderStatusLog::query()
            ->where('order_id', $order->id)
            ->where('event', OrderStatusLogEvent::OrderItemCancelled->value)
            ->count())->toBe(1)
        ->and(AuditLog::query()
            ->where('entity_type', 'order_item')
            ->where('entity_id', $ticketItem->order_item_id)
            ->where('action', AuditLogAction::OrderItemVoided->value)
            ->count())->toBe(1);
});

test('order item cancellation is blocked after a payment is recorded', function () {
    [$organization, $tableSession, , , $ticketItem] = createPrompt121SentOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 121 Paid Order Canceller']);

    attachPrompt121Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::CancelOrders,
    ]);
    ManualPayment::factory()
        ->forTableSession($tableSession)
        ->create(['recorded_by_user_id' => $waiter->id]);

    expect(fn () => app(CancelOrderItemAction::class)->handle(
        orderItem: $ticketItem->orderItem,
        cancelledBy: $waiter,
        reason: 'This must not alter a recorded bill.',
    ))->toThrow(BusinessRuleViolation::class)
        ->and($ticketItem->orderItem->fresh()->cancelled_at)->toBeNull()
        ->and($ticketItem->fresh()->status)->toBe(KitchenTicketItemStatus::New);
});

test('order item cancellation is blocked for terminal order or table session state', function (?OrderStatus $orderStatus, ?TableSessionStatus $sessionStatus) {
    [$organization, $tableSession, $order, , $ticketItem] = createPrompt121SentOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 121 Terminal State Canceller']);

    attachPrompt121Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::CancelOrders,
    ]);

    if ($orderStatus instanceof OrderStatus) {
        $order->forceFill(['status' => $orderStatus])->save();
    }

    if ($sessionStatus instanceof TableSessionStatus) {
        $tableSession->forceFill(['status' => $sessionStatus])->save();
    }

    expect(fn () => app(CancelOrderItemAction::class)->handle(
        orderItem: $ticketItem->orderItem,
        cancelledBy: $waiter,
        reason: 'Terminal state must protect the history.',
    ))->toThrow(BusinessRuleViolation::class)
        ->and($ticketItem->orderItem->fresh()->cancelled_at)->toBeNull();
})->with([
    'paid order' => [OrderStatus::Paid, null],
    'closed order' => [OrderStatus::Closed, null],
    'paid table session' => [null, TableSessionStatus::Paid],
    'closed table session' => [null, TableSessionStatus::Closed],
]);

test('staff without cancel order permission cannot cancel an order item', function () {
    [$organization, , , , $ticketItem] = createPrompt121SentOrderScenario();
    $viewer = User::factory()->create(['name' => 'Prompt 121 Read Only Waiter']);

    attachPrompt121Staff($viewer, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
    ]);

    expect(fn () => app(CancelOrderItemAction::class)->handle(
        orderItem: $ticketItem->orderItem,
        cancelledBy: $viewer,
        reason: 'Read-only staff must not cancel.',
    ))->toThrow(BusinessRuleViolation::class)
        ->and($ticketItem->orderItem->fresh()->cancelled_at)->toBeNull();
});

test('waiter cannot cancel another table session item by changing the browser supplied id', function () {
    [$organization, $tableSession] = createPrompt121SentOrderScenario();
    [, , , , $foreignTicketItem] = createPrompt121SentOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 121 Scoped Canceller']);

    attachPrompt121Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::CancelOrders,
    ]);

    Livewire::actingAs($waiter)
        ->test(OrderFulfilment::class, ['tableSessionId' => $tableSession->id])
        ->set('orderItemCancellationReason', 'Attempted cross-table cancellation.')
        ->call('cancelOrderItem', $foreignTicketItem->order_item_id)
        ->assertHasErrors('order_item_cancellation');

    expect($foreignTicketItem->orderItem->fresh()->cancelled_at)->toBeNull()
        ->and(OrderStatusLog::query()
            ->where('order_id', $foreignTicketItem->kitchenTicket->order_id)
            ->where('event', OrderStatusLogEvent::OrderItemCancelled->value)
            ->exists())->toBeFalse();
});

test('order cancellation action requires a non empty reason', function () {
    [$organization, , $order] = createPrompt121SentOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 121 No Reason']);

    attachPrompt121Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::CancelOrders,
    ]);

    app(ChangeOrderStatusAction::class)->handle(
        order: $order,
        newStatus: OrderStatus::Cancelled,
        changedBy: $waiter,
        reason: '   ',
    );
})->throws(ValidationException::class);

function createPrompt121SentOrderScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 121 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 121 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 121 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 121 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 121 Table',
            'display_number' => '121',
            'status' => ServicePointStatus::HasNewOrder,
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
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create(['status' => QrCodeStatus::Active]);
    $kitchenDepartment = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Prompt 121 Kitchen',
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 121 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Prompt 121 Category']);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($kitchenDepartment, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 121 Pizza',
            'price_cents' => 1200,
        ]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $guest->id,
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->for($menuItem, 'menuItem')
        ->create([
            'item_name' => 'Prompt 121 Pizza',
            'quantity' => 1,
            'unit_price_cents' => 1200,
            'modifier_total_cents' => 0,
            'total_price_cents' => 1200,
            'selected_modifiers' => [],
            'comment' => 'Cancel test',
        ]);

    $dispatcher = User::factory()->create(['name' => 'Prompt 121 Dispatcher']);
    attachPrompt121Staff($dispatcher, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
    ]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $dispatcher);
    $order = app(SendOrderToKitchenBarAction::class)->handle($order, $dispatcher);
    $ticketItem = KitchenTicketItem::query()
        ->whereHas('kitchenTicket', function ($query) use ($order): void {
            $query->where('order_id', $order->id);
        })
        ->firstOrFail();

    return [$organization, $tableSession, $order, $kitchenDepartment, $ticketItem, $guest, $qrCode];
}

function prompt121GuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt121Staff(User $user, Organization $organization, SystemRole $roleCode, array $permissions = []): Role
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
