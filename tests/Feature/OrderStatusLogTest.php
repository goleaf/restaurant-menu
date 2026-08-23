<?php

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\UpdateDraftOrderItemByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('order status log schema keeps actor and status history fields', function () {
    expect(Schema::hasTable('order_status_logs'))->toBeTrue()
        ->and(Schema::hasColumns('order_status_logs', [
            'branch_id',
            'service_point_id',
            'table_session_id',
            'draft_order_id',
            'order_id',
            'actor_user_id',
            'actor_guest_id',
            'actor_type',
            'actor_name',
            'event',
            'status_type',
            'previous_status',
            'new_status',
            'reason',
            'metadata',
            'occurred_at',
        ]))->toBeTrue()
        ->and(OrderStatusLogEvent::values())->toBe([
            'draft_created',
            'draft_edited',
            'draft_sent_to_waiter',
            'draft_confirmed',
            'draft_rejected',
            'draft_returned_to_draft',
            'order_status_changed',
            'order_sent_to_kitchen_bar',
            'order_cancelled',
            'order_item_cancelled',
        ]);
});

test('order status log action infers order draft and system status types', function () {
    [, $tableSession] = createPrompt57GuestDraftScenario();
    $draftOrder = DraftOrder::factory()->for($tableSession)->create();
    $order = Order::factory()
        ->for($tableSession)
        ->for($draftOrder, 'draftOrder')
        ->create();
    $action = app(CreateOrderStatusLogAction::class);

    $orderLog = $action->handle(OrderStatusLogEvent::OrderStatusChanged, order: $order);
    $draftLog = $action->handle(OrderStatusLogEvent::DraftEdited, draftOrder: $draftOrder);
    $systemLog = $action->handle(OrderStatusLogEvent::DraftCreated);

    expect($orderLog->status_type)->toBe('order')
        ->and($draftLog->status_type)->toBe('draft_order')
        ->and($systemLog->status_type)->toBeNull();
});

test('guest draft creation send and waiter confirmation create status logs', function () {
    [$organization, $tableSession, $guest, $menuItem] = createPrompt57GuestDraftScenario();

    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
        comment: 'No salt',
    );

    $draftOrder = $draftOrderItem->draftOrder()->firstOrFail();
    $logs = OrderStatusLog::query()
        ->where('draft_order_id', $draftOrder->id)
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->event)->toBe(OrderStatusLogEvent::DraftCreated)
        ->and($logs[0]->actor_guest_id)->toBe($guest->id)
        ->and($logs[0]->actor_type)->toBe('guest')
        ->and($logs[0]->actor_name)->toBe('Ana')
        ->and($logs[0]->previous_status)->toBeNull()
        ->and($logs[0]->new_status)->toBe(DraftOrderStatus::Draft->value)
        ->and($logs[0]->branch_id)->toBe($tableSession->branch_id)
        ->and($logs[0]->service_point_id)->toBe($tableSession->service_point_id)
        ->and($logs[1]->event)->toBe(OrderStatusLogEvent::DraftEdited)
        ->and($logs[1]->metadata['operation'])->toBe('guest_item_added')
        ->and($logs[1]->metadata['draft_order_item_id'])->toBe($draftOrderItem->id);

    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    $sentLog = OrderStatusLog::query()
        ->where('draft_order_id', $draftOrder->id)
        ->where('event', OrderStatusLogEvent::DraftSentToWaiter->value)
        ->firstOrFail();

    expect($sentLog->actor_guest_id)->toBe($guest->id)
        ->and($sentLog->previous_status)->toBe(DraftOrderStatus::Draft->value)
        ->and($sentLog->new_status)->toBe(DraftOrderStatus::SentToWaiter->value)
        ->and($sentLog->metadata['items_count'])->toBe(1);

    $waiter = User::factory()->create(['name' => 'Prompt 57 Waiter']);
    attachPrompt57Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder->fresh(), $waiter);

    $confirmLog = OrderStatusLog::query()
        ->where('draft_order_id', $draftOrder->id)
        ->where('order_id', $order->id)
        ->where('event', OrderStatusLogEvent::DraftConfirmed->value)
        ->firstOrFail();

    expect($confirmLog->actor_user_id)->toBe($waiter->id)
        ->and($confirmLog->actor_type)->toBe('user')
        ->and($confirmLog->actor_name)->toBe('Prompt 57 Waiter')
        ->and($confirmLog->previous_status)->toBe(DraftOrderStatus::SentToWaiter->value)
        ->and($confirmLog->new_status)->toBe(DraftOrderStatus::ConvertedToOrder->value)
        ->and($confirmLog->metadata['order_status'])->toBe(OrderStatus::ConfirmedByWaiter->value)
        ->and($confirmLog->metadata['items_count'])->toBe(1);
});

test('manual order status change updates order and writes status log', function () {
    [$organization, , $guest, $menuItem] = createPrompt57GuestDraftScenario();
    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $guest->tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
    );
    $draftOrder = $draftOrderItem->draftOrder()->firstOrFail();
    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    $manager = User::factory()->create(['name' => 'Prompt 57 Manager']);
    attachPrompt57Staff($manager, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
        SystemPermission::CancelOrders,
    ]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder->fresh(), $manager);

    $order = app(ChangeOrderStatusAction::class)->handle(
        order: $order,
        newStatus: OrderStatus::InProgress,
        changedBy: $manager,
        reason: 'Kitchen started',
    );

    $statusLog = $order->statusLogs()
        ->where('event', OrderStatusLogEvent::OrderStatusChanged->value)
        ->firstOrFail();

    expect($order->status)->toBe(OrderStatus::InProgress)
        ->and($statusLog->actor_user_id)->toBe($manager->id)
        ->and($statusLog->actor_name)->toBe('Prompt 57 Manager')
        ->and($statusLog->previous_status)->toBe(OrderStatus::ConfirmedByWaiter->value)
        ->and($statusLog->new_status)->toBe(OrderStatus::InProgress->value)
        ->and($statusLog->reason)->toBe('Kitchen started')
        ->and($statusLog->metadata['source'])->toBe('manual_status_change');

    $order = app(ChangeOrderStatusAction::class)->handle(
        order: $order,
        newStatus: OrderStatus::SentToKitchenBar,
        changedBy: $manager,
        reason: 'Send to kitchen and bar',
    );

    $kitchenLog = $order->statusLogs()
        ->where('event', OrderStatusLogEvent::OrderSentToKitchenBar->value)
        ->firstOrFail();

    expect($order->status)->toBe(OrderStatus::SentToKitchenBar)
        ->and($kitchenLog->previous_status)->toBe(OrderStatus::InProgress->value)
        ->and($kitchenLog->new_status)->toBe(OrderStatus::SentToKitchenBar->value)
        ->and($kitchenLog->reason)->toBe('Send to kitchen and bar');

    $order = app(ChangeOrderStatusAction::class)->handle(
        order: $order,
        newStatus: OrderStatus::Cancelled,
        changedBy: $manager,
        reason: 'Guest changed plans',
    );

    $cancelLog = $order->statusLogs()
        ->where('event', OrderStatusLogEvent::OrderCancelled->value)
        ->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelLog->previous_status)->toBe(OrderStatus::SentToKitchenBar->value)
        ->and($cancelLog->new_status)->toBe(OrderStatus::Cancelled->value)
        ->and($cancelLog->reason)->toBe('Guest changed plans');
});

test('waiter draft edit records staff actor and waiter review transition', function () {
    [$organization, , $guest, $menuItem] = createPrompt57GuestDraftScenario();
    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $guest->tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
    );
    $draftOrder = $draftOrderItem->draftOrder()->firstOrFail();
    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    $waiter = User::factory()->create(['name' => 'Prompt 57 Editor']);
    attachPrompt57Staff($waiter, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
    ]);

    app(UpdateDraftOrderItemByWaiterAction::class)->handle(
        draftOrderItem: $draftOrderItem,
        editedBy: $waiter,
        quantity: 2,
        selectedModifierOptions: [],
        comment: 'Bring first',
    );

    $editLog = OrderStatusLog::query()
        ->where('draft_order_id', $draftOrder->id)
        ->where('event', OrderStatusLogEvent::DraftEdited->value)
        ->where('actor_user_id', $waiter->id)
        ->firstOrFail();

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::WaiterReview)
        ->and($editLog->actor_type)->toBe('user')
        ->and($editLog->actor_name)->toBe('Prompt 57 Editor')
        ->and($editLog->previous_status)->toBe(DraftOrderStatus::SentToWaiter->value)
        ->and($editLog->new_status)->toBe(DraftOrderStatus::WaiterReview->value)
        ->and($editLog->metadata['operation'])->toBe('waiter_item_updated')
        ->and($editLog->metadata['draft_order_item_id'])->toBe($draftOrderItem->id);
});

function createPrompt57GuestDraftScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 57 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 57 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 57 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 57 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 57 Table',
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
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 57 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Main']);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Soup',
            'price_cents' => 600,
        ]);

    return [$organization, $tableSession, $guest, $menuItem];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt57Staff(User $user, Organization $organization, array $permissions): Role
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
