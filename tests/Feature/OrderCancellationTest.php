<?php

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\AuditLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Kitchen\Dashboard as KitchenDashboard;
use App\Livewire\PublicQr\OrderStatuses;
use App\Livewire\Waiter\TableDetail;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicketItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderStatusLog;
use App\Models\Organization;
use App\Models\Permission;
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
    [$organization, $tableSession, $order, $kitchenDepartment, $ticketItem] = createPrompt121SentOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 121 Waiter']);
    $chef = User::factory()->create(['name' => 'Prompt 121 Chef']);

    attachPrompt121Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::CancelOrders,
    ]);
    attachPrompt121Staff($chef, $organization, SystemRole::HeadChef);

    $ticketItem->forceFill(['status' => KitchenTicketItemStatus::Ready])->save();

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSee('Cancel order')
        ->assertSee('Some positions are already ready or served.')
        ->set('orderCancellationReason', '   ')
        ->call('cancelOrder')
        ->assertHasErrors('orderCancellationReason')
        ->set('orderCancellationReason', 'Guest asked to cancel after a long wait.')
        ->call('cancelOrder')
        ->assertHasNoErrors()
        ->assertSee('Order cancelled.')
        ->assertSet('table.draft.order_status_value', OrderStatus::Cancelled->value)
        ->assertSet('table.draft.cancellation_reason', 'Guest asked to cancel after a long wait.');

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

    Livewire::test(OrderStatuses::class, [
        'tableSessionId' => $tableSession->id,
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
            'price' => '12.00',
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
            'unit_price' => '12.00',
            'modifier_total' => '0.00',
            'total_price' => '12.00',
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

    return [$organization, $tableSession, $order, $kitchenDepartment, $ticketItem];
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
