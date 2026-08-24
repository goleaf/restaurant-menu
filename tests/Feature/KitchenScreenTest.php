<?php

use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Kitchen\Dashboard as KitchenDashboard;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchUser;
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

test('kitchen ticket items have kitchen work statuses', function () {
    expect(Schema::hasColumn('kitchen_ticket_items', 'status'))->toBeTrue()
        ->and(KitchenTicketItemStatus::values())->toBe([
            'new',
            'in_progress',
            'ready',
            'cancelled',
        ])
        ->and(array_keys(KitchenTicketItemStatus::options()))->toBe([
            'new',
            'in_progress',
            'ready',
        ])
        ->and(SystemPermission::ViewKitchen->value)->toBe('view_kitchen');
});

test('head chef sees only selected department tickets and updates item status', function () {
    [$organization, $kitchen, $bar, $kitchenItem, $barItem] = createPrompt61KitchenScenario();
    $headChef = User::factory()->create(['name' => 'Prompt 61 Head Chef']);

    attachPrompt61Staff($headChef, $organization, SystemRole::HeadChef);

    Livewire::actingAs($headChef)
        ->test(KitchenDashboard::class)
        ->assertSet('selectedDepartmentId', (string) $kitchen->id)
        ->assertSee('data-department-priority-queue', false)
        ->assertSee('data-priority-row', false)
        ->assertSee('min-h-operational-touch', false)
        ->assertSee('Prompt 61 Pizza')
        ->assertSee('Prompt 61 Table')
        ->assertSee('Prompt 61 Hall')
        ->assertSee('Crispy crust')
        ->assertSee('Size: Large')
        ->assertSee(__('ui.departments.dashboard.nacat'))
        ->assertSee(__('ui.departments.dashboard.gotovo'))
        ->assertSee('oldest first')
        ->assertDontSee('Prompt 61 Coffee')
        ->call('setItemStatus', $kitchenItem->id, KitchenTicketItemStatus::InProgress->value)
        ->assertHasNoErrors()
        ->assertSee('In progress')
        ->set('selectedDepartmentId', (string) $bar->id)
        ->assertSee('Prompt 61 Coffee')
        ->assertDontSee('Prompt 61 Pizza')
        ->call('setItemStatus', $barItem->id, KitchenTicketItemStatus::Ready->value)
        ->assertHasNoErrors()
        ->assertSee('Ready');

    expect($kitchenItem->fresh()->status)->toBe(KitchenTicketItemStatus::InProgress)
        ->and($barItem->fresh()->status)->toBe(KitchenTicketItemStatus::Ready);
});

test('view kitchen permission can open kitchen screen without chef role', function () {
    [$organization] = createPrompt61KitchenScenario();
    $staff = User::factory()->create(['name' => 'Prompt 61 Kitchen Viewer']);

    attachPrompt61Staff($staff, $organization, SystemRole::Waiter, [
        SystemPermission::ViewKitchen,
    ]);

    $this->actingAs($staff)
        ->get(route('restaurant.kitchen.dashboard'))
        ->assertSuccessful()
        ->assertSee('Kitchen screen');
});

test('staff without kitchen role or permission cannot open kitchen screen', function () {
    [$organization] = createPrompt61KitchenScenario();
    $staff = User::factory()->create(['name' => 'Prompt 61 No Kitchen']);

    attachPrompt61Staff($staff, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
    ]);

    $this->actingAs($staff)
        ->get(route('restaurant.kitchen.dashboard'))
        ->assertForbidden();
});

test('active branch assignment limits kitchen departments', function () {
    [$organization, $kitchen] = createPrompt61KitchenScenario();
    $secondBrand = Brand::factory()->for($organization)->create(['name' => 'Prompt 61 Other Brand']);
    $secondBranch = Branch::factory()
        ->for($organization)
        ->for($secondBrand)
        ->create(['name' => 'Prompt 61 Other Branch']);
    $otherDepartment = KitchenDepartment::factory()
        ->for($secondBranch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Prompt 61 Other Kitchen',
        ]);
    $cook = User::factory()->create(['name' => 'Prompt 61 Cook']);
    $role = attachPrompt61Staff($cook, $organization, SystemRole::Cook);

    $branchUser = new BranchUser;
    $branchUser->forceFill([
        'organization_id' => $organization->id,
        'branch_id' => $kitchen->branch_id,
        'user_id' => $cook->id,
        'role_id' => $role->id,
        'status' => OrganizationUserStatus::Active->value,
        'assigned_at' => now(),
        'assigned_by_user_id' => null,
    ])->save();

    $component = Livewire::actingAs($cook)
        ->test(KitchenDashboard::class)
        ->assertSet('selectedDepartmentId', (string) $kitchen->id)
        ->assertDontSee('Prompt 61 Other Kitchen');

    expect(collect($component->get('departments'))->pluck('id')->all())
        ->not->toContain($otherDepartment->id);
});

test('department tickets are sorted by oldest sent time first', function () {
    [$organization, $kitchen, , $kitchenItem] = createPrompt61KitchenScenario();
    $currentTicket = $kitchenItem->kitchenTicket()->firstOrFail();
    $currentTicket->loadMissing(['order', 'tableSession']);
    $olderDraft = DraftOrder::factory()
        ->for($currentTicket->tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now()->subMinutes(21),
        ]);
    $olderOrder = Order::factory()
        ->create([
            'branch_id' => $currentTicket->branch_id,
            'service_point_id' => $currentTicket->service_point_id,
            'table_session_id' => $currentTicket->table_session_id,
            'draft_order_id' => $olderDraft->id,
            'confirmed_at' => now()->subMinutes(20),
        ]);
    $olderTicket = KitchenTicket::factory()
        ->for($olderOrder)
        ->create([
            'branch_id' => $currentTicket->branch_id,
            'service_point_id' => $currentTicket->service_point_id,
            'table_session_id' => $currentTicket->table_session_id,
            'kitchen_department_id' => $kitchen->id,
            'department_type' => $kitchen->type,
            'department_name' => $kitchen->name,
            'sent_at' => now()->subMinutes(20),
        ]);
    $olderOrderItem = OrderItem::factory()
        ->for($olderOrder)
        ->create([
            'table_session_guest_id' => $kitchenItem->table_session_guest_id,
            'menu_item_id' => $kitchenItem->menu_item_id,
            'guest_name' => 'Ana',
            'item_name' => 'Prompt 61 Old Soup',
            'quantity' => 1,
            'selected_modifiers' => [],
            'comment' => 'Serve first',
        ]);

    KitchenTicketItem::factory()
        ->for($olderTicket, 'kitchenTicket')
        ->create([
            'order_item_id' => $olderOrderItem->id,
            'table_session_guest_id' => $kitchenItem->table_session_guest_id,
            'menu_item_id' => $kitchenItem->menu_item_id,
            'guest_name' => 'Ana',
            'item_name' => 'Prompt 61 Old Soup',
            'quantity' => 1,
            'selected_modifiers' => [],
            'comment' => 'Serve first',
        ]);
    $headChef = User::factory()->create(['name' => 'Prompt 61 Sorting Chef']);

    attachPrompt61Staff($headChef, $organization, SystemRole::HeadChef);

    $component = Livewire::actingAs($headChef)
        ->test(KitchenDashboard::class)
        ->assertSeeInOrder(['Prompt 61 Old Soup', 'Prompt 61 Pizza']);

    expect(collect($component->get('tickets'))->pluck('id')->first())->toBe($olderTicket->id);
});

test('kitchen delay timer exposes attention and delayed states at their exact thresholds', function () {
    $this->travelTo('2026-08-23 12:00:00');

    [$organization, $kitchen, , $kitchenItem] = createPrompt61KitchenScenario();
    $headChef = User::factory()->create(['name' => 'Prompt 61 Delay Chef']);

    attachPrompt61Staff($headChef, $organization, SystemRole::HeadChef);

    $ticket = $kitchenItem->kitchenTicket()->firstOrFail();
    $ticket->update(['sent_at' => now()->subMinutes(9)->subSeconds(59)]);

    $component = Livewire::actingAs($headChef)
        ->test(KitchenDashboard::class)
        ->assertSet('selectedDepartmentId', (string) $kitchen->id)
        ->assertSet('tickets.0.delay_state', 'on-track')
        ->assertSet('tickets.0.elapsed_label', '09:59')
        ->assertSet('tickets.0.delay_label', null)
        ->assertSee(__('ui.departments.dashboard.delay_status.on_track'))
        ->assertSeeHtml('data-kitchen-delay-timer');

    $ticket->update(['sent_at' => now()->subMinutes(10)]);

    $component
        ->call('refreshDepartment')
        ->assertSet('tickets.0.delay_state', 'attention')
        ->assertSet('tickets.0.elapsed_label', '10:00')
        ->assertSet('tickets.0.delay_label', null)
        ->assertSee(__('ui.departments.dashboard.delay_status.attention'));

    $ticket->update(['sent_at' => now()->subMinutes(15)->subSeconds(5)]);

    $component
        ->call('refreshDepartment')
        ->assertSet('tickets.0.delay_state', 'delayed')
        ->assertSet('tickets.0.elapsed_label', '15:05')
        ->assertSet('tickets.0.delay_label', '00:05')
        ->assertSee(__('ui.departments.dashboard.delay_status.delayed'))
        ->assertSee(__('ui.departments.dashboard.delay_by', ['time' => '00:05']));
});

function createPrompt61KitchenScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 61 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 61 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 61 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 61 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 61 Table',
            'display_number' => 'T-61',
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
            'name' => 'Prompt 61 Kitchen',
            'sort_order' => 10,
        ]);
    $bar = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Bar,
            'name' => 'Prompt 61 Bar',
            'sort_order' => 20,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 61 Menu',
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
            'name' => 'Prompt 61 Pizza',
            'price_cents' => 1100,
        ]);
    $coffee = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($bar, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 61 Coffee',
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
            'item_name' => 'Prompt 61 Pizza',
            'quantity' => 1,
            'unit_price_cents' => 1100,
            'modifier_total_cents' => 200,
            'total_price_cents' => 1300,
            'selected_modifiers' => [
                [
                    'group_name' => 'Size',
                    'option_name' => 'Large',
                    'price_delta_cents' => 200,
                ],
            ],
            'comment' => 'Crispy crust',
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($ana, 'guest')
        ->for($coffee, 'menuItem')
        ->create([
            'item_name' => 'Prompt 61 Coffee',
            'quantity' => 2,
            'unit_price_cents' => 300,
            'modifier_total_cents' => 0,
            'total_price_cents' => 600,
            'selected_modifiers' => [],
        ]);

    $dispatcher = User::factory()->create(['name' => 'Prompt 61 Dispatcher']);

    attachPrompt61Staff($dispatcher, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
    ]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $dispatcher);
    app(SendOrderToKitchenBarAction::class)->handle($order, $dispatcher);

    $kitchenItem = KitchenTicketItem::query()
        ->whereHas('kitchenTicket', function ($query) use ($kitchen): void {
            $query->where('kitchen_department_id', $kitchen->id);
        })
        ->firstOrFail();
    $barItem = KitchenTicketItem::query()
        ->whereHas('kitchenTicket', function ($query) use ($bar): void {
            $query->where('kitchen_department_id', $bar->id);
        })
        ->firstOrFail();

    return [$organization, $kitchen, $bar, $kitchenItem, $barItem];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt61Staff(User $user, Organization $organization, SystemRole $roleCode, array $permissions = []): Role
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
