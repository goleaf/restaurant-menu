<?php

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\ManualPayment;
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
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-04 12:00:00'));

    $this->seed(SystemPermissionsSeeder::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('reports viewer sees cached basic analytics for demo data', function () {
    [$user] = createPrompt69AnalyticsContext();

    $payload = app(BuildBasicAnalyticsDashboardAction::class)->handle($user);
    $analytics = $payload['analytics'];

    expect($payload['has_access'])->toBeTrue()
        ->and($analytics['orders_today_count'])->toBe(2)
        ->and($analytics['orders_today_total'])->toBe('€30.00')
        ->and($analytics['average_check'])->toBe('€15.00')
        ->and($analytics['active_tables_count'])->toBe(1)
        ->and($analytics['closed_sessions_count'])->toBe(1)
        ->and($analytics['cancelled_orders_count'])->toBe(1)
        ->and($analytics['popular_items'][0]['item_name'])->toBe('Pizza')
        ->and($analytics['popular_items'][0]['quantity'])->toBe(2)
        ->and(collect($analytics['popular_items'])->pluck('item_name')->all())->not->toContain('Cancelled Burger');

    $this->actingAs($user)
        ->get(route('restaurant.dashboard'))
        ->assertOk()
        ->assertSee('data-layout="restaurant-dashboard"', false)
        ->assertSeeText(__('reports.title'))
        ->assertSeeText(__('reports.revenue.net_total'))
        ->assertSeeText('€30.00')
        ->assertSeeText('Pizza');
});

test('restaurant dashboard hides analytics without view reports access', function () {
    $user = User::factory()->create(['name' => 'No Reports']);

    $this->actingAs($user)
        ->get(route('restaurant.dashboard'))
        ->assertOk()
        ->assertDontSeeText('Orders today')
        ->assertSeeText('Restaurant dashboard access appears when the user has branch-level operational or reporting access.');
});

test('empty analytics day uses branch currency for zero amounts', function () {
    $organization = Organization::factory()->create(['name' => 'Empty Reports Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Empty Reports Brand']);
    Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Empty Reports Branch',
            'currency' => 'EUR',
        ]);
    $user = User::factory()->create(['name' => 'Empty Reports Viewer']);
    attachPrompt69ReportsViewer($user, $organization);

    $analytics = app(BuildBasicAnalyticsDashboardAction::class)->handle($user)['analytics'];

    expect($analytics['orders_today_count'])->toBe(0)
        ->and($analytics['orders_today_total'])->toBe('€0.00')
        ->and($analytics['average_check'])->toBe('€0.00')
        ->and($analytics['popular_items'])->toBe([]);
});

test('order changes invalidate the cached analytics snapshot', function () {
    [$user, $branch, $servicePoint, $activeSession] = createPrompt69AnalyticsContext();
    $action = app(BuildBasicAnalyticsDashboardAction::class);
    $payload = $action->handle($user);
    $cacheKey = $payload['analytics']['cache_key'];

    expect(analyticsCacheStore()->has($cacheKey))->toBeTrue();

    createPrompt69Order(
        branch: $branch,
        servicePoint: $servicePoint,
        tableSession: $activeSession,
        itemName: 'Tea',
        quantity: 1,
        totalPriceCents: 700,
        confirmedAt: CarbonImmutable::parse('2026-06-04 11:30:00'),
    );

    expect(analyticsCacheStore()->has($cacheKey))->toBeFalse();

    $updatedAnalytics = $action->handle($user)['analytics'];

    expect($updatedAnalytics['orders_today_count'])->toBe(3)
        ->and($updatedAnalytics['orders_today_total'])->toBe('€37.00')
        ->and($updatedAnalytics['average_check'])->toBe('€12.33');
});

test('analytics cache can be invalidated for several branches at once', function () {
    [$user, $branch] = createPrompt69AnalyticsContext();
    $action = app(BuildBasicAnalyticsDashboardAction::class);
    $cacheKey = $action->handle($user)['analytics']['cache_key'];

    expect(analyticsCacheStore()->has($cacheKey))->toBeTrue();

    BuildBasicAnalyticsDashboardAction::forgetForBranches([$branch->id, 0]);

    expect(analyticsCacheStore()->has($cacheKey))->toBeFalse();
});

test('payment and session changes invalidate analytics cache', function () {
    [$user, $branch, $servicePoint, $activeSession] = createPrompt69AnalyticsContext();
    $action = app(BuildBasicAnalyticsDashboardAction::class);
    $cacheKey = $action->handle($user)['analytics']['cache_key'];

    expect(analyticsCacheStore()->has($cacheKey))->toBeTrue();

    ManualPayment::factory()
        ->for($branch)
        ->for($servicePoint)
        ->for($activeSession, 'tableSession')
        ->create([
            'amount_cents' => 3000,
            'currency' => 'EUR',
            'paid_at' => now(),
        ]);

    expect(analyticsCacheStore()->has($cacheKey))->toBeFalse();

    $action->handle($user);

    expect(analyticsCacheStore()->has($cacheKey))->toBeTrue();

    $activeSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now(),
    ])->save();

    expect(analyticsCacheStore()->has($cacheKey))->toBeFalse();

    $updatedAnalytics = $action->handle($user)['analytics'];

    expect($updatedAnalytics['active_tables_count'])->toBe(0)
        ->and($updatedAnalytics['closed_sessions_count'])->toBe(2);
});

function createPrompt69AnalyticsContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 69 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 69 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 69 Branch',
            'currency' => 'EUR',
        ]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Analytics Table',
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    $activeSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);

    TableSession::factory()
        ->forServicePoint($servicePoint)
        ->waiterOpened()
        ->create([
            'status' => TableSessionStatus::Closed,
            'started_at' => CarbonImmutable::parse('2026-06-04 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-06-04 10:00:00'),
        ]);

    $pizzaOrder = createPrompt69Order(
        branch: $branch,
        servicePoint: $servicePoint,
        tableSession: $activeSession,
        itemName: 'Pizza',
        quantity: 2,
        totalPriceCents: 2000,
        confirmedAt: CarbonImmutable::parse('2026-06-04 10:15:00'),
        status: OrderStatus::Served,
    );
    OrderItem::factory()
        ->for($pizzaOrder)
        ->cancelled()
        ->create([
            'item_name' => 'Cancelled Burger',
            'quantity' => 100,
            'total_price_cents' => 90000,
        ]);
    createPrompt69Order(
        branch: $branch,
        servicePoint: $servicePoint,
        tableSession: $activeSession,
        itemName: 'Coffee',
        quantity: 1,
        totalPriceCents: 1000,
        confirmedAt: CarbonImmutable::parse('2026-06-04 10:45:00'),
    );
    createPrompt69Order(
        branch: $branch,
        servicePoint: $servicePoint,
        tableSession: $activeSession,
        itemName: 'Old Dinner',
        quantity: 1,
        totalPriceCents: 9900,
        confirmedAt: CarbonImmutable::parse('2026-06-03 20:00:00'),
    );
    createPrompt69Order(
        branch: $branch,
        servicePoint: $servicePoint,
        tableSession: $activeSession,
        itemName: 'Cancelled Soup',
        quantity: 1,
        totalPriceCents: 500,
        confirmedAt: CarbonImmutable::parse('2026-06-04 11:00:00'),
        status: OrderStatus::Cancelled,
    )->update([
        'updated_at' => CarbonImmutable::parse('2026-06-04 11:15:00'),
    ]);

    $user = User::factory()->create(['name' => 'Reports Viewer']);
    attachPrompt69ReportsViewer($user, $organization);

    return [$user, $branch, $servicePoint, $activeSession];
}

function createPrompt69Order(
    Branch $branch,
    ServicePoint $servicePoint,
    TableSession $tableSession,
    string $itemName,
    int $quantity,
    int $totalPriceCents,
    CarbonImmutable $confirmedAt,
    OrderStatus $status = OrderStatus::ConfirmedByWaiter,
): Order {
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create();
    $order = Order::factory()
        ->for($branch)
        ->for($servicePoint)
        ->for($tableSession)
        ->for($draftOrder, 'draftOrder')
        ->create([
            'status' => $status,
            'confirmed_at' => $confirmedAt,
            'total_price_cents' => $totalPriceCents,
            'currency' => 'EUR',
        ]);

    OrderItem::factory()
        ->for($order)
        ->create([
            'guest_name' => 'Ana',
            'item_name' => $itemName,
            'quantity' => $quantity,
            'unit_price_cents' => intdiv($totalPriceCents, max($quantity, 1)),
            'total_price_cents' => $totalPriceCents,
        ]);

    return $order;
}

function attachPrompt69ReportsViewer(User $user, Organization $organization): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();
    $viewReports = Permission::query()
        ->where('code', SystemPermission::ViewReports->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($viewReports->id, ['enabled' => true]);

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

function analyticsCacheStore(): Repository
{
    return Cache::store(BuildBasicAnalyticsDashboardAction::cacheStore());
}
