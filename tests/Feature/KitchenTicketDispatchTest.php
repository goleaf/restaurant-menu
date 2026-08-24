<?php

use App\Actions\Orders\CancelOrderItemAction;
use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrder;
use App\Livewire\Waiter\TableDetail\DraftReview;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderItem;
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
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('kitchen ticket schema stores order dispatch split by department', function () {
    expect(Schema::hasTable('kitchen_tickets'))->toBeTrue()
        ->and(Schema::hasColumns('kitchen_tickets', [
            'id',
            'order_id',
            'branch_id',
            'service_point_id',
            'table_session_id',
            'kitchen_department_id',
            'department_type',
            'department_name',
            'status',
            'sent_by_user_id',
            'sent_at',
            'metadata',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('kitchen_ticket_items', [
            'id',
            'kitchen_ticket_id',
            'order_item_id',
            'table_session_guest_id',
            'menu_item_id',
            'guest_name',
            'item_name',
            'quantity',
            'selected_modifiers',
            'comment',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(KitchenTicketStatus::values())->toBe(['sent']);
});

test('confirmed order can be sent to kitchen bar with tickets split by departments', function () {
    [$organization, $servicePoint, $tableSession, $draftOrder, $ana, $kitchen, $bar] = createPrompt60SentDraftScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 60 Waiter']);
    attachPrompt60Staff($waiter, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
    ]);

    DraftOrderItem::query()
        ->where('draft_order_id', $draftOrder->id)
        ->where('item_name', 'Prompt 60 Pizza')
        ->firstOrFail()
        ->forceFill(['variant_name' => 'Large portion'])
        ->save();

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);

    $kitchen->update(['name' => 'Renamed kitchen after confirmation']);
    $bar->update(['name' => 'Renamed bar after confirmation']);

    $order = app(SendOrderToKitchenBarAction::class)->handle($order, $waiter);
    $tickets = KitchenTicket::query()
        ->with(['items'])
        ->where('order_id', $order->id)
        ->orderBy('department_type')
        ->orderBy('department_name')
        ->get();

    expect($order->status)->toBe(OrderStatus::SentToKitchenBar)
        ->and($order->metadata['sent_to_kitchen_bar'])->toBeTrue()
        ->and($order->metadata['sent_to_kitchen'])->toBeTrue()
        ->and($order->metadata['sent_to_bar'])->toBeTrue()
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Cooking)
        ->and($tickets)->toHaveCount(2);

    $kitchenTicket = $tickets->firstWhere('kitchen_department_id', $kitchen->id);
    $barTicket = $tickets->firstWhere('kitchen_department_id', $bar->id);

    expect($kitchenTicket)->not->toBeNull()
        ->and($kitchenTicket->department_type)->toBe(KitchenDepartmentType::Kitchen->value)
        ->and($kitchenTicket->department_name)->toBe('Prompt 60 Kitchen')
        ->and($kitchenTicket->items)->toHaveCount(1)
        ->and($kitchenTicket->items->first()->item_name)->toBe('Prompt 60 Pizza · Large portion')
        ->and($barTicket)->not->toBeNull()
        ->and($barTicket->department_type)->toBe(KitchenDepartmentType::Bar->value)
        ->and($barTicket->department_name)->toBe('Prompt 60 Bar')
        ->and($barTicket->items)->toHaveCount(1)
        ->and($barTicket->items->first()->item_name)->toBe('Prompt 60 Coffee');

    $dispatchLog = OrderStatusLog::query()
        ->where('order_id', $order->id)
        ->where('event', OrderStatusLogEvent::OrderSentToKitchenBar->value)
        ->firstOrFail();

    expect($dispatchLog->actor_user_id)->toBe($waiter->id)
        ->and($dispatchLog->previous_status)->toBe(OrderStatus::ConfirmedByWaiter->value)
        ->and($dispatchLog->new_status)->toBe(OrderStatus::SentToKitchenBar->value)
        ->and($dispatchLog->metadata['tickets_count'])->toBe(2);

    app(SendOrderToKitchenBarAction::class)->handle($order, $waiter);

    expect(KitchenTicket::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and(OrderStatusLog::query()
            ->where('order_id', $order->id)
            ->where('event', OrderStatusLogEvent::OrderSentToKitchenBar->value)
            ->count())->toBe(1);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $ana->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt60publictoken',
    ])
        ->assertSet('draftStatusValue', DraftOrderStatus::ConvertedToOrder->value)
        ->assertSet('orderStatusValue', OrderStatus::SentToKitchenBar->value)
        ->assertSee(__('guest.statuses.service.accepted_description'));
});

test('dispatch routes items without department snapshots to the default kitchen', function () {
    [$organization, , , $draftOrder, , $kitchen] = createPrompt60SentDraftScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 60 Default Kitchen Waiter']);
    attachPrompt60Staff($waiter, $organization, [
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
    ]);
    $menuItemIds = DraftOrderItem::query()
        ->where('draft_order_id', $draftOrder->id)
        ->whereNotNull('menu_item_id')
        ->pluck('menu_item_id');
    MenuItem::query()->whereIn('id', $menuItemIds)->update(['kitchen_department_id' => null]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);

    $tickets = KitchenTicket::query()->where('order_id', $order->id)->get();

    expect($tickets)->toHaveCount(1)
        ->and($tickets->first()->kitchen_department_id)->toBe($kitchen->id)
        ->and($tickets->first()->department_type)->toBe(KitchenDepartmentType::Kitchen->value);
});

test('confirm permission includes mandatory dispatch while send-only staff cannot confirm', function () {
    [$organization, , $tableSession, $draftOrder] = createPrompt60SentDraftScenario();
    $reviewer = User::factory()->create(['name' => 'Prompt 60 Reviewer']);
    $dispatcher = User::factory()->create(['name' => 'Prompt 60 Dispatcher']);

    attachPrompt60Staff($reviewer, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
    ]);
    attachPrompt60Staff($dispatcher, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::SendToKitchen,
    ]);
    $sendToKitchenPermission = Permission::query()
        ->where('code', SystemPermission::SendToKitchen->value)
        ->firstOrFail();
    $confirmOrdersPermission = Permission::query()
        ->where('code', SystemPermission::ConfirmOrders->value)
        ->firstOrFail();

    $reviewer->permissionOverrides()->syncWithoutDetaching([
        $sendToKitchenPermission->id => ['enabled' => false],
    ]);
    $dispatcher->permissionOverrides()->syncWithoutDetaching([
        $confirmOrdersPermission->id => ['enabled' => false],
    ]);

    Livewire::actingAs($dispatcher)
        ->test(DraftReview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('draftReview.draft.can_confirm', false)
        ->call('confirmDraft')
        ->assertHasErrors('draft_review');

    Livewire::actingAs($reviewer)
        ->test(DraftReview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('draftReview.draft.can_confirm', true)
        ->call('confirmDraft')
        ->assertHasNoErrors()
        ->assertSee(__('ui.livewire.waiter.tabledetail.zakaz_podtverzden_oficiantom_kuxnia_i_bar_po'));

    $servicePoint = $tableSession->servicePoint()->firstOrFail();

    expect(KitchenTicket::query()->count())->toBe(2)
        ->and($servicePoint->status)->toBe(ServicePointStatus::Cooking);
});

test('cancelled dispatched item is kept in history and becomes non actionable', function () {
    [$organization, , , $draftOrder, , $kitchen, $bar] = createPrompt60SentDraftScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 60 Pre-dispatch Canceller']);

    attachPrompt60Staff($waiter, $organization, [
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
        SystemPermission::CancelOrders,
    ]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);
    $pizza = OrderItem::query()
        ->where('order_id', $order->id)
        ->where('item_name_snapshot', 'Prompt 60 Pizza')
        ->firstOrFail();

    app(CancelOrderItemAction::class)->handle(
        orderItem: $pizza,
        cancelledBy: $waiter,
        reason: 'Guest cancelled before preparation started.',
    );
    expect($pizza->fresh()->cancelled_at)->not->toBeNull()
        ->and(OrderItem::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and($order->fresh()->total_price_cents)->toBe(600)
        ->and(KitchenTicket::query()
            ->where('order_id', $order->id)
            ->where('kitchen_department_id', $kitchen->id)
            ->exists())->toBeTrue()
        ->and($pizza->kitchenTicketItem()->firstOrFail()->status)->toBe(KitchenTicketItemStatus::Cancelled)
        ->and(KitchenTicket::query()
            ->where('order_id', $order->id)
            ->where('kitchen_department_id', $bar->id)
            ->exists())->toBeTrue();
});

function createPrompt60SentDraftScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 60 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 60 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 60 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 60 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 60 Table',
            'status' => ServicePointStatus::HasNewOrder,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $kitchen = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Prompt 60 Kitchen',
            'sort_order' => 10,
        ]);
    $bar = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Bar,
            'name' => 'Prompt 60 Bar',
            'sort_order' => 20,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 60 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Main']);
    $pizza = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($kitchen, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 60 Pizza',
            'price_cents' => 1100,
        ]);
    $coffee = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($bar, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 60 Coffee',
            'price_cents' => 300,
        ]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $ana->id,
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($zara, 'guest')
        ->for($pizza, 'menuItem')
        ->create([
            'item_name' => 'Prompt 60 Pizza',
            'quantity' => 1,
            'unit_price_cents' => 1100,
            'modifier_total_cents' => 0,
            'total_price_cents' => 1100,
            'selected_modifiers' => [],
            'comment' => 'Crispy crust',
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($ana, 'guest')
        ->for($coffee, 'menuItem')
        ->create([
            'item_name' => 'Prompt 60 Coffee',
            'quantity' => 2,
            'unit_price_cents' => 300,
            'modifier_total_cents' => 0,
            'total_price_cents' => 600,
            'selected_modifiers' => [],
        ]);

    return [$organization, $servicePoint, $tableSession, $draftOrder, $ana, $kitchen, $bar];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt60Staff(User $user, Organization $organization, array $permissions): Role
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
