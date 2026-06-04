<?php

use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Kitchen\Dashboard as KitchenDashboard;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrder;
use App\Livewire\Waiter\TableDetail as WaiterTableDetail;
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
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('kitchen ticket items store waiter served tracking', function () {
    expect(Schema::hasColumns('kitchen_ticket_items', [
        'served_at',
        'served_by_user_id',
    ]))->toBeTrue();
});

test('ready kitchen items appear for waiter and can be marked served', function () {
    [$organization, $servicePoint, $tableSession, $guest, $waiter, $cook, $order, $ticketItem] = createPrompt63ReadyItemScenario();

    Livewire::actingAs($cook)
        ->test(KitchenDashboard::class)
        ->assertSee('Prompt 63 Pizza')
        ->call('setItemStatus', $ticketItem->id, KitchenTicketItemStatus::Ready->value)
        ->assertHasNoErrors()
        ->assertSee('Ready');

    expect($ticketItem->fresh()->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and($order->fresh()->status)->toBe(OrderStatus::Ready)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::ReadyToServe);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt63publictoken',
    ])
        ->assertSet('serviceStatusValue', 'ready')
        ->assertSee('Статус заказа')
        ->assertSee('Готово');

    Livewire::actingAs($waiter)
        ->test(WaiterTableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('table.draft.order_status_value', OrderStatus::Ready->value)
        ->assertSet('table.draft.ready_ticket_item_count', 1)
        ->assertSet('table.draft.served_ticket_item_count', 0)
        ->assertSee($organization->name)
        ->assertSee('Kitchen/bar positions')
        ->assertSee('Prompt 63 Pizza')
        ->assertSee('Ready')
        ->assertSee('Mark served')
        ->call('markTicketItemServed', $ticketItem->id)
        ->assertHasNoErrors()
        ->assertSet('table.draft.order_status_value', OrderStatus::Served->value)
        ->assertSet('table.draft.served_ticket_item_count', 1)
        ->assertSee('Served')
        ->assertDontSee('Mark served');

    $servedTicketItem = $ticketItem->fresh();

    expect($servedTicketItem->served_at)->not->toBeNull()
        ->and($servedTicketItem->served_by_user_id)->toBe($waiter->id)
        ->and($order->fresh()->status)->toBe(OrderStatus::Served)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt63publictoken',
    ])
        ->assertSet('serviceStatusValue', 'served')
        ->assertSee('Статус заказа')
        ->assertSee('Подано');
});

function createPrompt63ReadyItemScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 63 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 63 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 63 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 63 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 63 Table',
            'display_number' => 'T-63',
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
            'guest_token' => 'prompt63guesttoken',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $kitchen = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Prompt 63 Kitchen',
            'sort_order' => 10,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 63 Menu',
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
            'name' => 'Prompt 63 Pizza',
            'price' => '14.00',
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
        ->for($pizza, 'menuItem')
        ->create([
            'item_name' => 'Prompt 63 Pizza',
            'quantity' => 1,
            'unit_price' => '14.00',
            'modifier_total' => '0.00',
            'total_price' => '14.00',
            'selected_modifiers' => [],
            'comment' => 'Ready handoff test',
        ]);

    $waiter = User::factory()->create(['name' => 'Prompt 63 Waiter']);
    $cook = User::factory()->create(['name' => 'Prompt 63 Cook']);

    attachPrompt63Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
    ]);
    attachPrompt63Staff($cook, $organization, SystemRole::Cook);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);
    $order = app(SendOrderToKitchenBarAction::class)->handle($order, $waiter);
    $ticketItem = KitchenTicketItem::query()
        ->whereHas('kitchenTicket', function ($query) use ($order): void {
            $query->where('order_id', $order->id);
        })
        ->firstOrFail();

    return [$organization, $servicePoint, $tableSession, $guest, $waiter, $cook, $order, $ticketItem];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt63Staff(User $user, Organization $organization, SystemRole $roleCode, array $permissions = []): Role
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
