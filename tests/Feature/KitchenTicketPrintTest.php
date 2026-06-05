<?php

use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
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
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
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

test('kitchen staff can open a browser print friendly ticket page', function () {
    [$organization, $kitchenTicket] = createPrompt127TicketScenario();
    $headChef = User::factory()->create(['name' => 'Prompt 127 Head Chef']);

    attachPrompt127Staff($headChef, $organization, SystemRole::HeadChef);

    $this->actingAs($headChef)
        ->get(prompt127TicketPrintUrl($kitchenTicket))
        ->assertOk()
        ->assertSee('data-page="department-ticket-print"', false)
        ->assertSeeText(__('Kitchen ticket print'))
        ->assertSeeText(__('Browser print'))
        ->assertSeeText(__('Print this ticket from the browser. No printer integration is required.'))
        ->assertSeeText('Prompt 127 Branch')
        ->assertSeeText('Prompt 127 Table')
        ->assertSeeText('Prompt 127 Hall')
        ->assertSeeText('#'.$kitchenTicket->order_id)
        ->assertSeeText('Prompt 127 Kitchen')
        ->assertSeeText('Prompt 127 Pizza')
        ->assertSeeText('Size: Large')
        ->assertSeeText('Crispy crust')
        ->assertSeeText('Zara')
        ->assertSee('x-on:click="window.print()"', false);
});

test('department dashboard links visible tickets to the print page', function () {
    [$organization, $kitchenTicket] = createPrompt127TicketScenario();
    $headChef = User::factory()->create(['name' => 'Prompt 127 Link Chef']);

    attachPrompt127Staff($headChef, $organization, SystemRole::HeadChef);

    Livewire::actingAs($headChef)
        ->test(KitchenDashboard::class)
        ->assertSee(__('Print'))
        ->assertSee(prompt127TicketPrintUrl($kitchenTicket), false);
});

test('department ticket print page keeps kitchen and bar access scoped', function () {
    [$organization, $kitchenTicket, $barTicket] = createPrompt127TicketScenario();
    $bartender = User::factory()->create(['name' => 'Prompt 127 Bartender']);
    $outsider = User::factory()->create(['name' => 'Prompt 127 Outsider']);

    attachPrompt127Staff($bartender, $organization, SystemRole::Bartender);
    attachPrompt127Staff($outsider, $organization, SystemRole::Marketer);

    $this->get(prompt127TicketPrintUrl($barTicket))
        ->assertRedirect(route('login'));

    $this->actingAs($bartender)
        ->get(prompt127TicketPrintUrl($barTicket))
        ->assertOk()
        ->assertSeeText('Prompt 127 Bar')
        ->assertSeeText('Prompt 127 Coffee')
        ->assertSeeText('Milk: Oat')
        ->assertSeeText('No ice');

    $this->actingAs($bartender)
        ->get(prompt127TicketPrintUrl($kitchenTicket))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->get(prompt127TicketPrintUrl($barTicket))
        ->assertForbidden();
});

function createPrompt127TicketScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 127 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 127 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 127 Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'currency' => 'EUR',
            'timezone' => 'Europe/Vilnius',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 127 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 127 Table',
            'display_number' => 'T-127',
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
            'name' => 'Prompt 127 Kitchen',
            'sort_order' => 10,
        ]);
    $bar = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Bar,
            'name' => 'Prompt 127 Bar',
            'sort_order' => 20,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 127 Menu',
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
            'name' => 'Prompt 127 Pizza',
            'price' => '11.00',
        ]);
    $coffee = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($bar, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 127 Coffee',
            'price' => '3.00',
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
            'item_name' => 'Prompt 127 Pizza',
            'quantity' => 1,
            'unit_price' => '11.00',
            'modifier_total' => '2.00',
            'total_price' => '13.00',
            'selected_modifiers' => [
                [
                    'group_name' => 'Size',
                    'option_name' => 'Large',
                    'price_delta' => '2.00',
                ],
            ],
            'comment' => 'Crispy crust',
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($ana, 'guest')
        ->for($coffee, 'menuItem')
        ->create([
            'item_name' => 'Prompt 127 Coffee',
            'quantity' => 2,
            'unit_price' => '3.00',
            'modifier_total' => '1.00',
            'total_price' => '7.00',
            'selected_modifiers' => [
                [
                    'group_name' => 'Milk',
                    'option_name' => 'Oat',
                    'price_delta' => '0.50',
                ],
            ],
            'comment' => 'No ice',
        ]);

    $dispatcher = User::factory()->create(['name' => 'Prompt 127 Dispatcher']);

    attachPrompt127Staff($dispatcher, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
    ]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $dispatcher);
    app(SendOrderToKitchenBarAction::class)->handle($order, $dispatcher);

    $kitchenTicket = KitchenTicket::query()
        ->where('kitchen_department_id', $kitchen->id)
        ->firstOrFail();
    $barTicket = KitchenTicket::query()
        ->where('kitchen_department_id', $bar->id)
        ->firstOrFail();

    return [$organization, $kitchenTicket, $barTicket];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt127Staff(User $user, Organization $organization, SystemRole $roleCode, array $permissions = []): Role
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

function prompt127TicketPrintUrl(KitchenTicket $ticket): string
{
    return route('restaurant.departments.tickets.print', $ticket);
}
