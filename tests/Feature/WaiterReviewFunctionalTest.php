<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\AddDraftOrderItemByWaiterAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\DeleteDraftOrderItemByWaiterAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
use App\Actions\Waiter\UpdateDraftOrderItemByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
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
use App\Models\KitchenTicketItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
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
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);

    Notification::fake();
});

test('waiter sees sent draft in dashboard and can open it for review', function () {
    $context = createPrompt355WaiterReviewContext();
    $waiter = createPrompt355Waiter($context['organization']);

    $dashboard = app(BuildWaiterDashboardAction::class)->handle($waiter);
    $branchPayload = collect($dashboard['branches'])
        ->firstWhere('id', $context['branch']->id);
    $draftPayload = collect($branchPayload['drafts'])
        ->firstWhere('id', $context['draftOrder']->id);
    $servicePointPayload = collect($branchPayload['service_points'])
        ->firstWhere('id', $context['servicePoint']->id);
    $servicePointDraftPayload = collect($servicePointPayload['sessions'])
        ->firstWhere('id', $context['tableSession']->id)['draft'];

    expect($dashboard['has_access'])->toBeTrue()
        ->and($dashboard['new_draft_count'])->toBe(1)
        ->and($branchPayload['new_draft_count'])->toBe(1)
        ->and($draftPayload['status_label'])->toBe(DraftOrderStatus::SentToWaiter->label())
        ->and($draftPayload['sent_by_guest_name'])->toBe('Ana')
        ->and($draftPayload['items_count'])->toBe(2)
        ->and($draftPayload['total'])->toBe('14.00 EUR')
        ->and($servicePointPayload['new_draft_count'])->toBe(1)
        ->and($servicePointDraftPayload['id'])->toBe($context['draftOrder']->id);

    $tableDetail = app(BuildWaiterTableDetailAction::class)
        ->handle($waiter, $context['tableSession']);

    expect($tableDetail['has_access'])->toBeTrue()
        ->and($tableDetail['table']['service_point']['name'])->toBe('Prompt 355 Table')
        ->and($tableDetail['table']['zone']['name'])->toBe('Prompt 355 Hall')
        ->and($tableDetail['table']['draft']['status_value'])->toBe(DraftOrderStatus::SentToWaiter->value)
        ->and($tableDetail['table']['draft']['can_confirm'])->toBeTrue()
        ->and($tableDetail['table']['draft']['can_reject'])->toBeTrue()
        ->and($tableDetail['table']['draft']['can_edit'])->toBeTrue()
        ->and($tableDetail['table']['current_draft_total'])->toBe('14.00 EUR');
});

test('waiter dashboard query count stays within its eager loaded budget', function () {
    $context = createPrompt355WaiterReviewContext();
    $waiter = createPrompt355Waiter($context['organization']);
    $dashboard = null;

    $waiter->unsetRelation('roles');
    $queryCount = countDatabaseQueries(function () use (&$dashboard, $waiter): void {
        $dashboard = app(BuildWaiterDashboardAction::class)->handle($waiter);
    });

    expect($queryCount)->toBeLessThanOrEqual(40)
        ->and($dashboard)->toBeArray()
        ->and($dashboard['new_draft_count'])->toBe(1);
});

test('waiter edits sent draft before order creation and activity log records edit operations', function () {
    $context = createPrompt355WaiterReviewContext();
    $waiter = createPrompt355Waiter($context['organization']);

    $updatedPizza = app(UpdateDraftOrderItemByWaiterAction::class)->handle(
        draftOrderItem: $context['pizzaDraftItem'],
        editedBy: $waiter,
        quantity: 2,
        selectedModifierOptions: [],
        comment: 'Extra basil',
    );
    $addedWater = app(AddDraftOrderItemByWaiterAction::class)->handle(
        draftOrder: $context['draftOrder'],
        guest: $context['boris'],
        menuItem: $context['waterItem'],
        editedBy: $waiter,
        quantity: 3,
        selectedModifierOptions: [],
        comment: 'Sparkling',
    );

    app(DeleteDraftOrderItemByWaiterAction::class)->handle(
        draftOrderItem: $context['waterDraftItem'],
        editedBy: $waiter,
    );

    $tableDetail = app(BuildWaiterTableDetailAction::class)
        ->handle($waiter, $context['tableSession']);
    $anaSection = collect($tableDetail['table']['guest_sections'])
        ->firstWhere('guest_id', $context['ana']->id);
    $borisSection = collect($tableDetail['table']['guest_sections'])
        ->firstWhere('guest_id', $context['boris']->id);
    $editLogs = OrderStatusLog::query()
        ->where('draft_order_id', $context['draftOrder']->id)
        ->where('event', OrderStatusLogEvent::DraftEdited->value)
        ->orderBy('id')
        ->get();

    expect($context['draftOrder']->fresh()->status)->toBe(DraftOrderStatus::WaiterReview)
        ->and($updatedPizza->fresh()->quantity)->toBe(2)
        ->and($updatedPizza->fresh()->comment)->toBe('Extra basil')
        ->and($updatedPizza->fresh()->total_price_cents)->toBe(2000)
        ->and($addedWater->fresh()->quantity)->toBe(3)
        ->and($addedWater->fresh()->comment)->toBe('Sparkling')
        ->and($addedWater->fresh()->total_price_cents)->toBe(1200)
        ->and(DraftOrderItem::query()->whereKey($context['waterDraftItem']->id)->exists())->toBeFalse()
        ->and($tableDetail['table']['current_draft_total'])->toBe('32.00 EUR')
        ->and($tableDetail['table']['item_count'])->toBe(2)
        ->and($anaSection['total'])->toBe('20.00 EUR')
        ->and($borisSection['total'])->toBe('12.00 EUR')
        ->and(Order::query()->count())->toBe(0)
        ->and(OrderItem::query()->count())->toBe(0)
        ->and(KitchenTicket::query()->count())->toBe(0)
        ->and(KitchenTicketItem::query()->count())->toBe(0)
        ->and($editLogs)->toHaveCount(3)
        ->and($editLogs->pluck('actor_user_id')->unique()->values()->all())->toBe([$waiter->id])
        ->and($editLogs->pluck('metadata.operation')->all())->toBe([
            'waiter_item_updated',
            'waiter_item_added',
            'waiter_item_deleted',
        ]);
});

test('waiter rejects draft with required reason and guests can see rejection reason', function () {
    $context = createPrompt355WaiterReviewContext();
    $waiter = createPrompt355Waiter($context['organization']);
    $reason = 'Pizza is unavailable tonight.';

    Livewire::actingAs($waiter)
        ->test(DraftReview::class, ['tableSessionId' => $context['tableSession']->id])
        ->set('rejectionReason', '   ')
        ->call('rejectDraft')
        ->assertHasErrors('rejectionReason');

    expect($context['draftOrder']->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter);

    app(RejectDraftOrderByWaiterAction::class)->handle(
        draftOrder: $context['draftOrder'],
        rejectedBy: $waiter,
        reason: $reason,
    );

    $rejectionLog = OrderStatusLog::query()
        ->where('draft_order_id', $context['draftOrder']->id)
        ->where('event', OrderStatusLogEvent::DraftRejected->value)
        ->firstOrFail();

    expect($context['draftOrder']->fresh()->status)->toBe(DraftOrderStatus::Rejected)
        ->and($context['draftOrder']->fresh()->rejection_reason)->toBe($reason)
        ->and($context['draftOrder']->fresh()->rejected_by_user_id)->toBe($waiter->id)
        ->and(Order::query()->count())->toBe(0)
        ->and($rejectionLog->actor_user_id)->toBe($waiter->id)
        ->and($rejectionLog->reason)->toBe($reason);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $context['tableSession']->id,
        'currentGuestId' => $context['ana']->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt355publictoken',
    ])
        ->assertSet('draftStatusValue', DraftOrderStatus::Rejected->value)
        ->assertSet('rejectionReason', $reason)
        ->assertSet('canEditDraft', false)
        ->assertSee($reason);
});

test('waiter confirms draft into order with snapshot data without department dispatch', function () {
    $context = createPrompt355WaiterReviewContext();
    $waiter = createPrompt355Waiter($context['organization']);

    expect(Order::query()->count())->toBe(0)
        ->and(OrderItem::query()->count())->toBe(0)
        ->and(KitchenTicket::query()->count())->toBe(0)
        ->and(KitchenTicketItem::query()->count())->toBe(0);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle(
        draftOrder: $context['draftOrder'],
        confirmedBy: $waiter,
    );

    $order = $order->fresh(['items']);
    $pizzaOrderItem = $order->items
        ->firstWhere('item_name_snapshot', 'Pizza Margherita');
    $waterOrderItem = $order->items
        ->firstWhere('item_name_snapshot', 'Still Water');
    $confirmationLog = OrderStatusLog::query()
        ->where('draft_order_id', $context['draftOrder']->id)
        ->where('order_id', $order->id)
        ->where('event', OrderStatusLogEvent::DraftConfirmed->value)
        ->firstOrFail();

    expect($context['draftOrder']->fresh()->status)->toBe(DraftOrderStatus::ConvertedToOrder)
        ->and($order->status)->toBe(OrderStatus::ConfirmedByWaiter)
        ->and($order->confirmed_by_user_id)->toBe($waiter->id)
        ->and($order->total_price_cents)->toBe(1400)
        ->and($order->metadata['sent_to_kitchen'])->toBeFalse()
        ->and($order->metadata['sent_to_bar'])->toBeFalse()
        ->and($order->items)->toHaveCount(2)
        ->and($pizzaOrderItem->guest_name_snapshot)->toBe('Ana')
        ->and($pizzaOrderItem->original_menu_item_id)->toBe($context['pizzaItem']->id)
        ->and($pizzaOrderItem->unit_price_snapshot_cents)->toBe(1000)
        ->and($pizzaOrderItem->item_description_snapshot)->toBe('Classic tomato pizza')
        ->and($pizzaOrderItem->kitchen_department_id)->toBe($context['kitchen']->id)
        ->and($pizzaOrderItem->kitchen_department_type)->toBe(KitchenDepartmentType::Kitchen->value)
        ->and($pizzaOrderItem->kitchen_department_name)->toBe('Hot Kitchen')
        ->and($pizzaOrderItem->modifiers_snapshot)->toBe([])
        ->and($waterOrderItem->guest_name_snapshot)->toBe('Boris')
        ->and($waterOrderItem->original_menu_item_id)->toBe($context['waterItem']->id)
        ->and($waterOrderItem->unit_price_snapshot_cents)->toBe(400)
        ->and($waterOrderItem->item_description_snapshot)->toBe('Chilled bottled water')
        ->and($waterOrderItem->kitchen_department_id)->toBe($context['bar']->id)
        ->and($waterOrderItem->kitchen_department_type)->toBe(KitchenDepartmentType::Bar->value)
        ->and($waterOrderItem->kitchen_department_name)->toBe('Main Bar')
        ->and($confirmationLog->actor_user_id)->toBe($waiter->id)
        ->and($confirmationLog->new_status)->toBe(DraftOrderStatus::ConvertedToOrder->value)
        ->and($confirmationLog->metadata['order_status'])->toBe(OrderStatus::ConfirmedByWaiter->value)
        ->and(KitchenTicket::query()->count())->toBe(0)
        ->and(KitchenTicketItem::query()->count())->toBe(0);
});

/**
 * @return array<string, mixed>
 */
function createPrompt355WaiterReviewContext(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 355 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 355 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 355 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Prompt 355 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 355 Table',
            'display_number' => '35',
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
    $boris = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Boris',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $kitchen = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Hot Kitchen',
            'sort_order' => 10,
        ]);
    $bar = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Bar,
            'name' => 'Main Bar',
            'sort_order' => 20,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 355 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => 'Mains',
            'is_active' => true,
        ]);
    $pizzaItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($kitchen, 'kitchenDepartment')
        ->create([
            'name' => 'Pizza Margherita',
            'description' => 'Classic tomato pizza',
            'price_cents' => 1000,
            'is_available' => true,
        ]);
    $waterItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($bar, 'kitchenDepartment')
        ->create([
            'name' => 'Still Water',
            'description' => 'Chilled bottled water',
            'price_cents' => 400,
            'is_available' => true,
        ]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $ana->id,
        ]);
    $pizzaDraftItem = DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($ana, 'guest')
        ->for($pizzaItem, 'menuItem')
        ->create([
            'item_name' => 'Pizza Margherita',
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'modifier_total_cents' => 0,
            'total_price_cents' => 1000,
            'selected_modifiers' => [],
            'comment' => 'No onion',
        ]);
    $waterDraftItem = DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($boris, 'guest')
        ->for($waterItem, 'menuItem')
        ->create([
            'item_name' => 'Still Water',
            'quantity' => 1,
            'unit_price_cents' => 400,
            'modifier_total_cents' => 0,
            'total_price_cents' => 400,
            'selected_modifiers' => [],
            'comment' => 'No ice',
        ]);

    return [
        'organization' => $organization,
        'brand' => $brand,
        'branch' => $branch,
        'areaNode' => $areaNode,
        'servicePoint' => $servicePoint,
        'tableSession' => $tableSession,
        'ana' => $ana,
        'boris' => $boris,
        'kitchen' => $kitchen,
        'bar' => $bar,
        'menu' => $menu,
        'category' => $category,
        'pizzaItem' => $pizzaItem,
        'waterItem' => $waterItem,
        'draftOrder' => $draftOrder,
        'pizzaDraftItem' => $pizzaDraftItem,
        'waterDraftItem' => $waterDraftItem,
    ];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function createPrompt355Waiter(Organization $organization, array $permissions = [
    SystemPermission::ViewOrders,
    SystemPermission::ConfirmOrders,
]): User
{
    $waiter = User::factory()->create(['name' => 'Prompt 355 Waiter']);
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
        $waiter->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $waiter;
}
