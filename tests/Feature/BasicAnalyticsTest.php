<?php

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
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
use App\Support\LocalizedDateFormatter;
use App\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-04 12:00:00'));

    $this->seed(SystemPermissionsSeeder::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

dataset('report cache strategies', [
    'analytics' => [BuildBasicAnalyticsDashboardAction::class, 'analytics', 240, 300],
    'restaurant dashboard' => [BuildRestaurantDashboardAction::class, 'dashboard', 45, 60],
]);

test('report caches refresh after the response in their original locale', function (
    string $actionClass, string $payloadKey, int $freshSeconds, int $maximumSeconds, string $locale,
) {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:30'));
    config()->set('cache.stores.database.lock_lottery', [0, 1]);
    [$user] = createPrompt69AnalyticsContext();
    app()->setLocale($locale);
    $action = app($actionClass);
    $cache = Cache::store($actionClass::cacheStore());
    $initial = $action->handle($user)[$payloadKey];
    $pending = app(DeferredCallbackCollection::class);

    $this->travel($freshSeconds - 1)->seconds();
    $freshQueries = countDatabaseQueries(function () use ($action, $user, $payloadKey, $initial): void {
        expect($action->handle($user)[$payloadKey])->toBe($initial);
    });
    expect($pending)->toHaveCount(0);

    $this->travel(1)->seconds();
    $staleQueries = countDatabaseQueries(function () use ($action, $user, $payloadKey, $initial): void {
        expect($action->handle($user)[$payloadKey])->toBe($initial);
    });
    expect($pending)->toHaveCount(1)
        ->and($staleQueries)->toBe($freshQueries)
        ->and($cache->get($initial['cache_key']))->toBe($initial);
    $expectedTimestamp = LocalizedDateFormatter::dateTime(CarbonImmutable::now());
    app()->setLocale($locale === 'ru' ? 'en' : 'ru');
    $terminationLocale = app()->getLocale();

    app(InvokeDeferredCallbacks::class)->terminate(Request::create('/'), response('ok'));

    $refreshed = $cache->get($initial['cache_key']);
    expect($refreshed['cached_at'])->toBe($expectedTimestamp)
        ->and($refreshed['cached_at'])->not->toBe($initial['cached_at'])
        ->and($refreshed['cache_key'])->toBe($initial['cache_key'])
        ->and(app()->getLocale())->toBe($terminationLocale)
        ->and($pending)->toHaveCount(0);
})->with('report cache strategies')->with(['en', 'lt', 'ru']);

test('report caches rebuild synchronously after their maximum age', function (
    string $actionClass, string $payloadKey, int $freshSeconds, int $maximumSeconds,
) {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:30'));
    [$user] = createPrompt69AnalyticsContext();
    $action = app($actionClass);
    $initial = $action->handle($user)[$payloadKey];

    $this->travel($maximumSeconds + 1)->seconds();
    $current = $action->handle($user)[$payloadKey];

    expect($current['cached_at'])->toBe(LocalizedDateFormatter::dateTime(CarbonImmutable::now()))
        ->and($current['cached_at'])->not->toBe($initial['cached_at'])
        ->and(app(DeferredCallbackCollection::class))->toHaveCount(0);
})->with('report cache strategies');

test('invalidated report caches are not restored by a pending refresh', function (
    string $actionClass, string $payloadKey, int $freshSeconds, int $maximumSeconds,
) {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:30'));
    [$user, , , $activeSession] = createPrompt69AnalyticsContext();
    $action = app($actionClass);
    $cache = Cache::store($actionClass::cacheStore());
    $initial = $action->handle($user)[$payloadKey];
    $this->travel($freshSeconds)->seconds();
    $action->handle($user);
    expect(app(DeferredCallbackCollection::class))->toHaveCount(1);

    $activeSession->forceFill(['ended_at' => now()])->save();

    expect($cache->has($initial['cache_key']))->toBeFalse()
        ->and($cache->has(Repository::FLEXIBLE_CREATED_KEY_PREFIX.$initial['cache_key']))->toBeFalse();

    app(InvokeDeferredCallbacks::class)->terminate(Request::create('/'), response('ok'));

    expect($cache->has($initial['cache_key']))->toBeFalse();
    $current = $action->handle($user)[$payloadKey];
    expect($current['cached_at'])->toBe(LocalizedDateFormatter::dateTime(CarbonImmutable::now()));
})->with('report cache strategies');

test('report cache refresh does not wait for another refresher or extend stale lifetime', function (
    string $actionClass, string $payloadKey, int $freshSeconds, int $maximumSeconds,
) {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:30'));
    [$user] = createPrompt69AnalyticsContext();
    $action = app($actionClass);
    $cache = Cache::store($actionClass::cacheStore());
    $initial = $action->handle($user)[$payloadKey];
    $this->travel($freshSeconds)->seconds();
    $action->handle($user);
    expect(app(DeferredCallbackCollection::class))->toHaveCount(1);
    $lock = $cache->lock('illuminate:cache:flexible:lock:'.$initial['cache_key'], 30);
    expect($lock->get())->toBeTrue();

    try {
        app(InvokeDeferredCallbacks::class)->terminate(Request::create('/'), response('ok'));
        expect($cache->get($initial['cache_key']))->toBe($initial);
    } finally {
        $lock->release();
    }

    $this->travel($maximumSeconds - $freshSeconds + 1)->seconds();
    expect($cache->has($initial['cache_key']))->toBeFalse();
})->with('report cache strategies');

test('report cache eviction removes displaced snapshots and cancels their pending refresh', function (
    string $actionClass, string $payloadKey, int $freshSeconds, int $maximumSeconds,
) {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:30'));
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branches = Branch::factory()
        ->count(52)
        ->for($organization)
        ->for($brand)
        ->sequence(fn (Sequence $sequence): array => ['name' => 'Report cache branch '.$sequence->index])
        ->create();
    $commonBranch = $branches->first();
    $accessibleIds = collect([$commonBranch->id]);
    $this->mock(ResolveWaiterAccessibleBranchIdsAction::class)
        ->shouldReceive('handle')
        ->andReturnUsing(function (User $user, SystemPermission $permission = SystemPermission::ViewOrders) use (&$accessibleIds) {
            return $permission === SystemPermission::ViewReports ? $accessibleIds : collect();
        });
    $action = app($actionClass);
    $cache = Cache::store($actionClass::cacheStore());
    $snapshots = [];

    foreach ($branches->skip(1) as $branch) {
        $accessibleIds = collect([$commonBranch->id, $branch->id]);
        $snapshot = $action->handle($user)[$payloadKey];
        $snapshots[] = $snapshot;

        if (count($snapshots) === 1) {
            $this->travel($freshSeconds)->seconds();
            expect($action->handle($user)[$payloadKey])->toBe($snapshot)
                ->and(app(DeferredCallbackCollection::class))->toHaveCount(1);
        }
    }

    $evictedKey = $snapshots[0]['cache_key'];
    expect($snapshots)->toHaveCount(51)
        ->and($cache->has($evictedKey))->toBeFalse()
        ->and($cache->has(Repository::FLEXIBLE_CREATED_KEY_PREFIX.$evictedKey))->toBeFalse();
    app(InvokeDeferredCallbacks::class)->terminate(Request::create('/'), response('ok'));
    expect($cache->has($evictedKey))->toBeFalse()
        ->and($action->handle($user)[$payloadKey])->toBe($snapshots[50]);

    foreach (array_slice($snapshots, 1) as $snapshot) {
        expect($cache->get($snapshot['cache_key']))->toBe($snapshot);
    }

    $actionClass::forgetForBranch($commonBranch->id);

    foreach ($snapshots as $snapshot) {
        expect($cache->has($snapshot['cache_key']))->toBeFalse()
            ->and($cache->has(Repository::FLEXIBLE_CREATED_KEY_PREFIX.$snapshot['cache_key']))->toBeFalse();
    }
})->with('report cache strategies');

test('report invalidation fences a snapshot published by an overlapping build', function (
    string $actionClass, string $payloadKey, int $freshSeconds, int $maximumSeconds, bool $deferred, int $writeDelay,
) {
    [$user, , , $session] = createPrompt69AnalyticsContext();
    $action = app($actionClass);
    $prefix = $payloadKey === 'analytics' ? 'analytics:dashboard:' : 'restaurant-dashboard:';
    $metric = $payloadKey === 'analytics' ? 'active_tables_count' : 'metrics.active_tables_count';

    if ($deferred) {
        $action->handle($user);
        $this->travel($freshSeconds)->seconds();
        $action->handle($user);
    }

    $interruptedKey = null;
    Event::listen(WritingManyKeys::class, function (WritingManyKeys $event) use (&$interruptedKey, $prefix, $session, $writeDelay): void {
        if ($interruptedKey !== null || ! str_starts_with($event->keys[0], $prefix)) {
            return;
        }

        $interruptedKey = $event->keys[0];
        $this->travel($writeDelay)->seconds();
        $session->forceFill(['status' => TableSessionStatus::Closed, 'ended_at' => now()])->save();
    });

    if ($deferred) {
        app(InvokeDeferredCallbacks::class)->terminate(Request::create('/'), response('ok'));
    } else {
        $action->handle($user);
    }

    expect($interruptedKey)->not->toBeNull();
    $current = $action->handle($user)[$payloadKey];

    expect(data_get($current, $metric))->toBe(0)
        ->and($current['cache_key'])->not->toBe($interruptedKey)
        ->and($action->handle($user)[$payloadKey])->toBe($current);
})->with('report cache strategies')->with([
    'cold build' => [false, 0],
    'deferred build' => [true, 0],
    'expired refresh lease' => [true, 31],
]);

test('report cache versioning keeps foreground query costs bounded', function (
    string $actionClass, string $payloadKey, int $freshSeconds, int $maximumSeconds,
): void {
    [$user] = createPrompt69AnalyticsContext();
    $action = app($actionClass);
    $operation = fn (): array => $action->handle($user);
    $cold = countDatabaseQueries($operation);
    $fresh = countDatabaseQueries($operation);
    $this->travel($freshSeconds)->seconds();
    $stale = countDatabaseQueries($operation);

    expect($cold)->toBe($payloadKey === 'analytics' ? 22 : 80)
        ->and($fresh)->toBe($payloadKey === 'analytics' ? 10 : 64)
        ->and($stale)->toBe($fresh);
})->with('report cache strategies');

test('report invalidation survives a registry key lost by overlapping registrations', function (
    string $actionClass, string $payloadKey, int $freshSeconds, int $maximumSeconds,
) {
    [$user, $branch, , $session] = createPrompt69AnalyticsContext();
    $otherBranch = Branch::factory()->create([
        'organization_id' => $branch->organization_id,
        'brand_id' => $branch->brand_id,
        'name' => 'Concurrent report branch',
    ]);
    $accessibleIds = collect([$branch->id]);
    $this->mock(ResolveWaiterAccessibleBranchIdsAction::class)
        ->shouldReceive('handle')
        ->andReturnUsing(function (User $user, SystemPermission $permission = SystemPermission::ViewOrders) use (&$accessibleIds) {
            return $permission === SystemPermission::ViewReports ? $accessibleIds : collect();
        });
    $action = app($actionClass);
    $cache = Cache::store($actionClass::cacheStore());
    $indexKey = ($payloadKey === 'analytics' ? 'analytics:dashboard' : 'restaurant-dashboard').':branch:'.$branch->id.':keys';
    $interleaved = false;
    $overlappingSnapshot = null;
    Event::listen(WritingKey::class, function (WritingKey $event) use (
        &$interleaved, &$overlappingSnapshot, &$accessibleIds, $indexKey, $branch, $otherBranch, $action, $user, $payloadKey,
    ): void {
        if ($interleaved || $event->key !== $indexKey) {
            return;
        }

        $interleaved = true;
        $accessibleIds = collect([$branch->id, $otherBranch->id]);
        $overlappingSnapshot = $action->handle($user)[$payloadKey];
        $accessibleIds = collect([$branch->id]);
    });

    $action->handle($user);

    expect($interleaved)->toBeTrue()
        ->and($cache->get($indexKey))->not->toContain($overlappingSnapshot['cache_key']);

    $session->forceFill(['status' => TableSessionStatus::Closed, 'ended_at' => now()])->save();
    $accessibleIds = collect([$branch->id, $otherBranch->id]);
    $current = $action->handle($user)[$payloadKey];
    $metric = $payloadKey === 'analytics' ? 'active_tables_count' : 'metrics.active_tables_count';

    expect(data_get($current, $metric))->toBe(0)
        ->and($current['cache_key'])->not->toBe($overlappingSnapshot['cache_key']);
})->with('report cache strategies');

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

test('analytics cache keeps localized snapshots separate and invalidates every language', function () {
    [$user, , , $activeSession] = createPrompt69AnalyticsContext();
    $action = app(BuildBasicAnalyticsDashboardAction::class);
    $snapshots = [];

    foreach (['en', 'lt', 'ru'] as $locale) {
        app()->setLocale($locale);
        $analytics = $action->handle($user)['analytics'];

        expect($analytics['cached_at'])->toBe(LocalizedDateFormatter::dateTime(CarbonImmutable::now()))
            ->and($analytics['orders_today_total'])->toBe(MoneyFormatter::formatCents(3000, 'EUR'))
            ->and($analytics['average_check'])->toBe(MoneyFormatter::formatCents(1500, 'EUR'));

        $snapshots[$locale] = $analytics;
    }

    expect(array_unique(array_column($snapshots, 'cache_key')))->toHaveCount(3);

    foreach ($snapshots as $locale => $analytics) {
        app()->setLocale($locale);

        expect($action->handle($user)['analytics'])->toBe($analytics)
            ->and(analyticsCacheStore()->has($analytics['cache_key']))->toBeTrue();
    }

    $activeSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now(),
    ])->save();

    foreach ($snapshots as $analytics) {
        expect(analyticsCacheStore()->has($analytics['cache_key']))->toBeFalse();
    }
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

    $nextCacheKey = $action->handle($user)['analytics']['cache_key'];

    expect($nextCacheKey)->not->toBe($cacheKey)
        ->and(analyticsCacheStore()->has($nextCacheKey))->toBeTrue();

    $activeSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now(),
    ])->save();

    expect(analyticsCacheStore()->has($nextCacheKey))->toBeFalse();

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
