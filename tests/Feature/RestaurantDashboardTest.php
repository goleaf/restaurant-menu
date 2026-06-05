<?php

use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-04 14:00:00'));

    $this->seed(SystemPermissionsSeeder::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('manager sees restaurant dashboard metrics and quick actions', function () {
    [$organization] = createPrompt70DashboardContext();
    $manager = User::factory()->create(['name' => 'Restaurant Manager']);
    attachPrompt70Staff($manager, $organization, SystemRole::Director, [
        SystemPermission::ViewReports,
        SystemPermission::ViewOrders,
        SystemPermission::ManageMenu,
        SystemPermission::ManageServicePoints,
        SystemPermission::GenerateQr,
        SystemPermission::ViewKitchen,
    ]);

    $dashboard = app(BuildRestaurantDashboardAction::class)->handle($manager)['dashboard'];

    expect($dashboard['can_view_reports'])->toBeTrue()
        ->and($dashboard['metrics']['active_tables_count'])->toBe(1)
        ->and($dashboard['metrics']['new_orders_to_waiter_count'])->toBe(1)
        ->and($dashboard['metrics']['cooking_orders_count'])->toBe(1)
        ->and($dashboard['metrics']['ready_positions_count'])->toBe(1)
        ->and($dashboard['metrics']['orders_today_total'])->toBe('32.00 EUR')
        ->and($dashboard['popular_items'][0]['item_name'])->toBe('Pasta')
        ->and(collect($dashboard['quick_actions'])->where('is_available', true)->pluck('label')->all())
        ->toContain('Menu', 'Tables', 'QR', 'Waiter screen', 'Kitchen', 'reports.title');

    $this->actingAs($manager)
        ->get(route('restaurant.dashboard'))
        ->assertOk()
        ->assertSee('data-layout="restaurant-dashboard"', false)
        ->assertSeeText(__('reports.title'))
        ->assertSeeText(__('reports.active_tables'))
        ->assertSeeText(__('reports.new_orders_to_waiter'))
        ->assertSeeText(__('reports.cooking_orders'))
        ->assertSeeText(__('reports.ready_positions'))
        ->assertSeeText(__('reports.revenue.net_total'))
        ->assertSeeText('32.00 EUR')
        ->assertSeeText('Pasta')
        ->assertSeeText(__('reports.quick_actions.title'))
        ->assertSeeText('Menu')
        ->assertSeeText('Tables')
        ->assertSeeText('QR')
        ->assertSeeText('Waiter screen')
        ->assertSeeText('Kitchen')
        ->assertSeeText(__('reports.title'));
});

test('waiter sees operational dashboard without report totals', function () {
    [$organization] = createPrompt70DashboardContext();
    $waiter = User::factory()->create(['name' => 'Dashboard Waiter']);
    attachPrompt70Staff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
    ]);

    $dashboard = app(BuildRestaurantDashboardAction::class)->handle($waiter)['dashboard'];

    expect($dashboard['can_view_reports'])->toBeFalse()
        ->and($dashboard['metrics']['active_tables_count'])->toBe(1)
        ->and($dashboard['metrics']['new_orders_to_waiter_count'])->toBe(1)
        ->and($dashboard['metrics']['cooking_orders_count'])->toBe(1)
        ->and($dashboard['metrics']['ready_positions_count'])->toBe(1)
        ->and($dashboard['metrics']['orders_today_total'])->toBeNull()
        ->and($dashboard['popular_items'])->toBe([])
        ->and(collect($dashboard['quick_actions'])->firstWhere('label', 'Tables')['is_available'])->toBeTrue()
        ->and(collect($dashboard['quick_actions'])->firstWhere('label', 'reports.title')['is_available'])->toBeFalse();

    $this->actingAs($waiter)
        ->get(route('restaurant.dashboard'))
        ->assertOk()
        ->assertSeeText(__('reports.title'))
        ->assertSeeText(__('reports.access_required'))
        ->assertSeeText(__('reports.access_required_popular_items'))
        ->assertDontSeeText('32.00 EUR');
});

test('restaurant dashboard cache is invalidated by draft and kitchen ticket item changes', function () {
    [$organization, , , , $sentDraft, $ticketItem] = createPrompt70DashboardContext();
    $manager = User::factory()->create(['name' => 'Cache Manager']);
    attachPrompt70Staff($manager, $organization, SystemRole::Director, [
        SystemPermission::ViewReports,
        SystemPermission::ViewOrders,
    ]);

    $action = app(BuildRestaurantDashboardAction::class);
    $cacheKey = $action->handle($manager)['dashboard']['cache_key'];

    expect(restaurantDashboardCacheStore()->has($cacheKey))->toBeTrue();

    $sentDraft->update(['status' => DraftOrderStatus::WaiterReview]);

    expect(restaurantDashboardCacheStore()->has($cacheKey))->toBeFalse();

    $action->handle($manager);

    expect(restaurantDashboardCacheStore()->has($cacheKey))->toBeTrue();

    $ticketItem->update(['served_at' => now()]);

    expect(restaurantDashboardCacheStore()->has($cacheKey))->toBeFalse();
});

function createPrompt70DashboardContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 70 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 70 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 70 Branch',
            'currency' => 'EUR',
        ]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Dashboard Table',
            'status' => ServicePointStatus::Cooking,
            'is_active' => true,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $sentDraft = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
        ]);
    $convertedDraft = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::ConvertedToOrder]);
    $order = Order::factory()
        ->for($branch)
        ->for($servicePoint)
        ->for($tableSession)
        ->for($convertedDraft, 'draftOrder')
        ->create([
            'status' => OrderStatus::SentToKitchenBar,
            'confirmed_at' => CarbonImmutable::parse('2026-06-04 12:10:00'),
            'total_price' => '32.00',
            'currency' => 'EUR',
        ]);
    $orderItem = OrderItem::factory()
        ->for($order)
        ->create([
            'guest_name' => 'Ana',
            'item_name' => 'Pasta',
            'quantity' => 2,
            'unit_price' => '16.00',
            'total_price' => '32.00',
        ]);
    $department = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Kitchen',
            'is_active' => true,
        ]);
    $ticket = KitchenTicket::factory()
        ->for($order)
        ->for($branch)
        ->for($servicePoint)
        ->for($tableSession)
        ->for($department, 'kitchenDepartment')
        ->create([
            'department_type' => KitchenDepartmentType::Kitchen,
            'department_name' => 'Kitchen',
            'sent_at' => now(),
        ]);
    $ticketItem = KitchenTicketItem::factory()
        ->for($ticket, 'kitchenTicket')
        ->for($orderItem, 'orderItem')
        ->create([
            'item_name' => 'Pasta',
            'quantity' => 2,
            'status' => KitchenTicketItemStatus::Ready,
            'served_at' => null,
        ]);

    return [$organization, $branch, $servicePoint, $tableSession, $sentDraft, $ticketItem];
}

function attachPrompt70Staff(User $user, Organization $organization, SystemRole $roleCode, array $permissions): Role
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

function restaurantDashboardCacheStore(): Repository
{
    return Cache::store(BuildRestaurantDashboardAction::cacheStore());
}
