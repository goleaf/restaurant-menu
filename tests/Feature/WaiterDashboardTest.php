<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
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
        ->assertSee($organization->name)
        ->assertSee($brand->name)
        ->assertSee($branch->name)
        ->assertSee('Window table')
        ->assertSee('Has new order')
        ->assertSee('Waiting review')
        ->assertSee('Anna')
        ->assertSee('19.50 EUR');

    $this->actingAs($waiter)
        ->get(route('restaurant.waiter.dashboard'))
        ->assertOk()
        ->assertSee('wire:poll.1s="refreshDashboard"', false);
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

    BranchUser::query()->create([
        'organization_id' => $organization->id,
        'branch_id' => $firstBranch->id,
        'user_id' => $waiter->id,
        'role_id' => $waiterRole->id,
        'status' => OrganizationUserStatus::Active,
        'assigned_at' => now(),
        'assigned_by_user_id' => null,
    ]);

    ServicePoint::factory()->for($firstBranch)->create(['name' => 'Assigned table']);
    ServicePoint::factory()->for($secondBranch)->create(['name' => 'Hidden table']);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
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
