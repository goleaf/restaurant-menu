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
use App\Livewire\Bar\Dashboard as BarDashboard;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicketItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('bartender sees only bar department drinks and updates item status', function () {
    [$organization, $kitchen, $bar, $barItem] = createPrompt62BarScenario();
    $bartender = User::factory()->create(['name' => 'Prompt 62 Bartender']);

    attachPrompt62Staff($bartender, $organization, SystemRole::Bartender);

    $component = Livewire::actingAs($bartender)
        ->test(BarDashboard::class)
        ->assertSet('selectedDepartmentId', (string) $bar->id)
        ->assertSee('Bar screen')
        ->assertSee('Prompt 62 Coffee')
        ->assertSee('Milk: Oat')
        ->assertSee('No ice')
        ->assertSee('T-62')
        ->assertSee('Timer')
        ->assertDontSee('Prompt 62 Pizza')
        ->assertDontSee('Prompt 62 Kitchen')
        ->call('setItemStatus', $barItem->id, KitchenTicketItemStatus::InProgress->value)
        ->assertHasNoErrors()
        ->assertSee('In progress');

    expect($barItem->fresh()->status)->toBe(KitchenTicketItemStatus::InProgress)
        ->and(collect($component->get('departments'))->pluck('id')->all())
        ->not->toContain($kitchen->id);
});

test('head chef can open bar screen when needed', function () {
    [$organization, , , $barItem] = createPrompt62BarScenario();
    $headChef = User::factory()->create(['name' => 'Prompt 62 Head Chef']);

    attachPrompt62Staff($headChef, $organization, SystemRole::HeadChef);

    Livewire::actingAs($headChef)
        ->test(BarDashboard::class)
        ->assertSee('Bar screen')
        ->assertSee('Prompt 62 Coffee')
        ->call('setItemStatus', $barItem->id, KitchenTicketItemStatus::Ready->value)
        ->assertHasNoErrors();

    expect($barItem->fresh()->status)->toBe(KitchenTicketItemStatus::Ready);
});

test('view orders or send to kitchen permissions can open bar screen', function (SystemPermission $permission) {
    [$organization] = createPrompt62BarScenario();
    $staff = User::factory()->create(['name' => 'Prompt 62 Permission Staff']);

    attachPrompt62Staff($staff, $organization, SystemRole::Marketer, [$permission]);

    $this->actingAs($staff)
        ->get(route('restaurant.bar.dashboard'))
        ->assertSuccessful()
        ->assertSee('Bar screen')
        ->assertSee('Prompt 62 Coffee');
})->with([
    'view orders' => SystemPermission::ViewOrders,
    'send to kitchen' => SystemPermission::SendToKitchen,
]);

test('staff without bar role or permission cannot open bar screen', function () {
    [$organization] = createPrompt62BarScenario();
    $staff = User::factory()->create(['name' => 'Prompt 62 No Bar']);

    attachPrompt62Staff($staff, $organization, SystemRole::Marketer);

    $this->actingAs($staff)
        ->get(route('restaurant.bar.dashboard'))
        ->assertForbidden();
});

function createPrompt62BarScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 62 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 62 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 62 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 62 Lounge']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 62 Table',
            'display_number' => 'T-62',
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
    $kitchen = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Prompt 62 Kitchen',
            'sort_order' => 10,
        ]);
    $bar = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Bar,
            'name' => 'Prompt 62 Bar',
            'sort_order' => 20,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 62 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Drinks']);
    $pizza = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($kitchen, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 62 Pizza',
            'price' => '12.00',
        ]);
    $coffee = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($bar, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 62 Coffee',
            'price' => '4.00',
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
        ->for($ana, 'guest')
        ->for($pizza, 'menuItem')
        ->create([
            'item_name' => 'Prompt 62 Pizza',
            'quantity' => 1,
            'unit_price' => '12.00',
            'modifier_total' => '0.00',
            'total_price' => '12.00',
            'selected_modifiers' => [],
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($ana, 'guest')
        ->for($coffee, 'menuItem')
        ->create([
            'item_name' => 'Prompt 62 Coffee',
            'quantity' => 2,
            'unit_price' => '4.00',
            'modifier_total' => '1.00',
            'total_price' => '10.00',
            'selected_modifiers' => [
                [
                    'group_name' => 'Milk',
                    'option_name' => 'Oat',
                    'price_delta' => '0.50',
                ],
            ],
            'comment' => 'No ice',
        ]);

    $dispatcher = User::factory()->create(['name' => 'Prompt 62 Dispatcher']);

    attachPrompt62Staff($dispatcher, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
    ]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $dispatcher);
    app(SendOrderToKitchenBarAction::class)->handle($order, $dispatcher);

    $barItem = KitchenTicketItem::query()
        ->whereHas('kitchenTicket', function ($query) use ($bar): void {
            $query->where('kitchen_department_id', $bar->id);
        })
        ->firstOrFail();

    return [$organization, $kitchen, $bar, $barItem];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt62Staff(User $user, Organization $organization, SystemRole $roleCode, array $permissions = []): Role
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
