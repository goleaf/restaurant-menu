<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
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
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('waiter dashboard requires authentication', function () {
    $this->get(route('restaurant.waiter.dashboard'))
        ->assertRedirect(route('login'));
});

test('waiter dashboard requires view orders permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('restaurant.waiter.dashboard'))
        ->assertForbidden();
});

test('waiter dashboard shows branch service points sessions and sent drafts', function () {
    [$organization, $brand, $branch] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Window table',
            'display_number' => '12',
            'status' => ServicePointStatus::HasNewOrder,
        ]);

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();

    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Anna']);

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
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Pasta',
            'quantity' => 2,
            'unit_price' => '9.75',
            'total_price' => '19.50',
        ]);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('servicePointCount', 1)
        ->assertSet('activeSessionCount', 1)
        ->assertSet('newDraftCount', 1)
        ->assertSet('branches.0.has_activity', true)
        ->assertSee($organization->name)
        ->assertSee($brand->name)
        ->assertSee($branch->name)
        ->assertSee('Window table')
        ->assertDontSee('truncate', false)
        ->assertSee('Has new order')
        ->assertSee('Waiting review')
        ->assertSee('Anna')
        ->assertSee('19.50 EUR');

    $this->actingAs($waiter)
        ->get(route('restaurant.waiter.dashboard'))
        ->assertOk()
        ->assertSee('wire:poll.visible.1s="refreshDashboard"', false);
});

test('waiter dashboard limits branches to active branch assignments when present', function () {
    [$organization, , $firstBranch] = createPrompt52Branch(branchName: 'Assigned Branch');
    $secondBrand = Brand::factory()->for($organization)->create(['name' => 'Second Brand']);
    $secondBranch = Branch::factory()
        ->for($organization)
        ->for($secondBrand)
        ->create(['name' => 'Unassigned Branch']);
    $waiter = User::factory()->create();
    $waiterRole = attachPrompt52Waiter($waiter, $organization);

    $branchUser = new BranchUser;
    $branchUser->forceFill([
        'organization_id' => $organization->id,
        'branch_id' => $firstBranch->id,
        'user_id' => $waiter->id,
        'role_id' => $waiterRole->id,
        'status' => OrganizationUserStatus::Active,
        'assigned_at' => now(),
        'assigned_by_user_id' => null,
    ])->save();

    ServicePoint::factory()->for($firstBranch)->create(['name' => 'Assigned table']);
    ServicePoint::factory()->for($secondBranch)->create(['name' => 'Hidden table']);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('branches.0.has_activity', false)
        ->assertSee('data-branch-activity="idle"', false)
        ->assertSee('Assigned Branch')
        ->assertSee('Assigned table')
        ->assertDontSee('Unassigned Branch')
        ->assertDontSee('Hidden table');
});

test('waiter dashboard refresh shows newly sent draft without websockets', function () {
    [$organization, , $branch] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Polling table']);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()->for($tableSession)->create(['guest_name' => 'Marta']);

    $component = Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('newDraftCount', 0)
        ->assertSee('Polling table');

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
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Soup',
            'total_price' => '7.00',
        ]);

    $component
        ->call('refreshDashboard')
        ->assertSet('newDraftCount', 1)
        ->assertSee('Marta')
        ->assertSee('7.00 EUR');
});

test('waiter dashboard groups tables by zones and surfaces urgent work', function () {
    [$organization, , $branch] = createPrompt52Branch(branchName: 'Prompt 91 Branch');
    $waiter = User::factory()->create(['name' => 'Prompt 91 Waiter']);
    $waiterRole = attachPrompt52Waiter($waiter, $organization);
    enablePrompt52Permission($waiterRole, SystemPermission::CloseTableSessions);

    $mainHall = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Main Hall']);
    $terrace = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Terrace']);

    $freeTable = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall, 'areaNode')
        ->create([
            'name' => 'Free Window',
            'display_number' => 'F1',
            'status' => ServicePointStatus::Free,
        ]);
    $newOrderTable = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall, 'areaNode')
        ->create([
            'name' => 'New Order Table',
            'display_number' => 'N1',
            'status' => ServicePointStatus::HasNewOrder,
        ]);
    $callTable = ServicePoint::factory()
        ->for($branch)
        ->for($terrace, 'areaNode')
        ->create([
            'name' => 'Call Terrace',
            'display_number' => 'T2',
            'status' => ServicePointStatus::WaitingWaiter,
        ]);
    $billTable = ServicePoint::factory()
        ->for($branch)
        ->for($terrace, 'areaNode')
        ->create([
            'name' => 'Bill Terrace',
            'display_number' => 'T3',
            'status' => ServicePointStatus::PaymentRequested,
        ]);
    $readyTable = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall, 'areaNode')
        ->create([
            'name' => 'Ready Table',
            'display_number' => 'R1',
            'status' => ServicePointStatus::ReadyToServe,
        ]);

    $newOrderSession = TableSession::factory()
        ->forServicePoint($newOrderTable)
        ->active()
        ->create();
    $newOrderGuest = TableSessionGuest::factory()
        ->for($newOrderSession)
        ->create(['guest_name' => 'Anna']);
    $draftOrder = DraftOrder::factory()
        ->for($newOrderSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $newOrderGuest->id,
        ]);
    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($newOrderGuest, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Prompt 91 Pasta',
            'quantity' => 1,
            'unit_price' => '12.00',
            'total_price' => '12.00',
        ]);

    $callSession = TableSession::factory()
        ->forServicePoint($callTable)
        ->active()
        ->create();
    $callGuest = TableSessionGuest::factory()
        ->for($callSession)
        ->create(['guest_name' => 'Boris']);
    WaiterCall::factory()
        ->forTableSession($callSession)
        ->create(['requested_by_guest_id' => $callGuest->id]);

    TableSession::factory()
        ->forServicePoint($billTable)
        ->active()
        ->create(['status' => TableSessionStatus::PaymentRequested]);

    $readySession = TableSession::factory()
        ->forServicePoint($readyTable)
        ->active()
        ->create();
    $readyGuest = TableSessionGuest::factory()
        ->for($readySession)
        ->create(['guest_name' => 'Clara']);
    $order = Order::factory()
        ->for($readySession)
        ->create([
            'branch_id' => $branch->id,
            'service_point_id' => $readyTable->id,
            'table_session_id' => $readySession->id,
            'status' => OrderStatus::Ready,
            'currency' => 'EUR',
        ]);
    $orderItem = OrderItem::factory()
        ->for($order)
        ->for($readyGuest, 'guest')
        ->create([
            'guest_name' => 'Clara',
            'guest_name_snapshot' => 'Clara',
            'item_name' => 'Prompt 91 Soup',
            'item_name_snapshot' => 'Prompt 91 Soup',
            'quantity' => 2,
        ]);
    $ticket = KitchenTicket::factory()
        ->for($order)
        ->create([
            'branch_id' => $branch->id,
            'service_point_id' => $readyTable->id,
            'table_session_id' => $readySession->id,
            'department_name' => 'Kitchen',
        ]);
    KitchenTicketItem::factory()
        ->for($ticket, 'kitchenTicket')
        ->for($orderItem, 'orderItem')
        ->create([
            'table_session_guest_id' => $readyGuest->id,
            'guest_name' => 'Clara',
            'item_name' => 'Prompt 91 Soup',
            'quantity' => 2,
            'status' => KitchenTicketItemStatus::Ready,
            'served_at' => null,
        ]);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('newDraftCount', 1)
        ->assertSet('waiterCallCount', 1)
        ->assertSet('billRequestCount', 1)
        ->assertSet('readyItemCount', 1)
        ->assertSee('Main Hall')
        ->assertSee('Terrace')
        ->assertSee('New orders')
        ->assertSee('Guest calls')
        ->assertSee('Bill requests')
        ->assertSee('Ready items')
        ->assertSee('Prompt 91 Soup')
        ->assertSee('Close table')
        ->assertSee('Free Window')
        ->assertSee('Open table')
        ->call('openTable', $freeTable->id)
        ->assertHasNoErrors()
        ->assertSee(__('ui.livewire.waiter.dashboard.stol_otkryt'));

    expect(TableSession::query()
        ->where('service_point_id', $freeTable->id)
        ->where('status', TableSessionStatus::Active->value)
        ->count())->toBe(1)
        ->and($freeTable->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

function createPrompt52Branch(
    string $organizationName = 'Waiter Group',
    string $brandName = 'Waiter Brand',
    string $branchName = 'Waiter Branch',
): array {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => $branchName,
            'city' => 'Vilnius',
            'currency' => 'EUR',
        ]);

    return [$organization, $brand, $branch, $owner->fresh()];
}

function attachPrompt52Waiter(User $user, Organization $organization): Role
{
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $viewOrders = Permission::query()
        ->where('code', SystemPermission::ViewOrders->value)
        ->firstOrFail();

    $waiterRole->permissions()->updateExistingPivot($viewOrders->id, ['enabled' => true]);

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $waiterRole;
}

function enablePrompt52Permission(Role $role, SystemPermission $permissionCode): void
{
    $permission = Permission::query()
        ->where('code', $permissionCode->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
}
