<?php

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Reports\BranchReportQuery;
use App\Support\MoneyFormatter;
use App\Support\Reports\BranchReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('branch report repeated names have bounded hydration', function (int $orderCount): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:00', 'UTC'));
    $branch = Branch::factory()->create(['timezone' => 'UTC', 'name' => 'Reporting fixture']);
    $session = TableSession::factory()->recycle($branch)->active()->create();
    Order::withoutEvents(function () use ($session, $orderCount): void {
        Order::factory()->forTableSession($session)->count($orderCount)->create(['total_price_cents' => 3000])
            ->each(function (Order $order): void {
                OrderItem::factory()->for($order)->count(3)->create([
                    'table_session_guest_id' => null,
                    'item_name' => 'ŠALTIBARŠČIAI',
                    'total_price_cents' => 1000,
                ]);
            });
    });
    $user = User::factory()->create();
    $this->mock(ResolveWaiterAccessibleBranchIdsAction::class)->shouldReceive('handle')->andReturn(collect([$branch->id]));
    $action = app(BuildBasicAnalyticsDashboardAction::class);
    $hydrated = [];
    Event::listen('eloquent.retrieved: *', function (string $event, array $models) use (&$hydrated): void {
        $class = $models[0]::class;
        $hydrated[$class] = ($hydrated[$class] ?? 0) + 1;
    });
    gc_collect_cycles();
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $started = hrtime(true);
    DB::enableQueryLog();
    $analytics = $action->handle($user)['analytics'];
    $metrics = [
        'orders' => $orderCount,
        'items' => $orderCount * 3,
        'queries' => count(DB::getQueryLog()),
        'elapsed_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
        'hydrated' => $hydrated,
        'peak_growth_bytes' => memory_get_peak_usage() - $memory,
        'payload_bytes' => strlen(json_encode($analytics, JSON_THROW_ON_ERROR)),
    ];
    DB::disableQueryLog();
    DB::flushQueryLog();
    if (getenv('REPORT_METRICS')) {
        fwrite(STDERR, json_encode($metrics, JSON_THROW_ON_ERROR).PHP_EOL);
    }

    expect($analytics['orders_today_count'])->toBe($orderCount)
        ->and($analytics['orders_today_total'])->toBe(MoneyFormatter::formatCents($orderCount * 3000, 'EUR'))
        ->and($analytics['popular_items'])->toHaveCount(1)
        ->and($analytics['popular_items'][0]['quantity'])->toBe($orderCount * 3)
        ->and($analytics['popular_items'][0]['total'])->toBe(MoneyFormatter::formatCents($orderCount * 3000, 'EUR'))
        ->and($hydrated[Order::class] ?? 0)->toBeLessThanOrEqual(2)
        ->and($hydrated[OrderItem::class] ?? 0)->toBeLessThanOrEqual(2);
})->with(['200 orders' => 200, '2000 orders' => 2000]);

test('report calendar ranges include leap month boundaries and DST days', function (string $preset, ?string $from, ?string $to, string $now, string $start, string $end): void {
    $branch = Branch::factory()->make(['id' => 1, 'timezone' => 'Europe/Vilnius']);
    $period = BranchReportPeriod::fromSelection(collect([$branch]), $preset, $from, $to, CarbonImmutable::parse($now, 'UTC'));

    expect($period->ranges[1]['start'])->toBe($start)
        ->and($period->ranges[1]['end'])->toBe($end);
})->with([
    'today spring DST 23 hours' => ['today', null, null, '2026-03-29 12:00:00', '2026-03-28 22:00:00', '2026-03-29 21:00:00'],
    'today autumn DST 25 hours' => ['today', null, null, '2026-10-25 12:00:00', '2026-10-24 21:00:00', '2026-10-25 22:00:00'],
    'yesterday leap date' => ['yesterday', null, null, '2024-03-01 12:00:00', '2024-02-28 22:00:00', '2024-02-29 22:00:00'],
    'last seven calendar days' => ['last7', null, null, '2026-04-01 12:00:00', '2026-03-25 22:00:00', '2026-04-01 21:00:00'],
    'inclusive custom month end' => ['custom', '2024-02-29', '2024-03-01', '2026-06-04 12:00:00', '2024-02-28 22:00:00', '2024-03-01 22:00:00'],
]);

test('report ranges reject malformed dates and more than thirty one calendar days', function (string $preset, ?string $from, ?string $to): void {
    BranchReportPeriod::fromSelection(collect(), $preset, $from, $to);
})->throws(ValidationException::class)->with([
    ['custom', '2026-02-30', '2026-03-01'],
    ['custom', '2026-01-01', '2026-02-01'],
    ['custom', '2026-03-02', '2026-03-01'],
    ['custom', null, null],
    ['unbounded', null, null],
]);

test('report periods use each branch local date and change cache context at its midnight', function (): void {
    $branches = collect([
        Branch::factory()->make(['id' => 1, 'timezone' => 'Pacific/Auckland']),
        Branch::factory()->make(['id' => 2, 'timezone' => 'America/Los_Angeles']),
    ]);
    $before = BranchReportPeriod::fromSelection($branches, now: CarbonImmutable::parse('2026-06-04 11:59:59', 'UTC'));
    $after = BranchReportPeriod::fromSelection($branches, now: CarbonImmutable::parse('2026-06-04 12:00:00', 'UTC'));
    expect($after->ranges[1]['date_from'])->toBe('2026-06-05')
        ->and($after->ranges[2]['date_from'])->toBe('2026-06-04')
        ->and($after->fingerprint())->not->toBe($before->fingerprint());
});

test('report scopes confirmed orders and actual payment dates independently without mixing currencies', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-03-29 12:00:00', 'UTC'));
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $session = TableSession::factory()->recycle($branch)->active()->create();
    $factory = Order::factory()->forTableSession($session);
    $first = $factory->create(['confirmed_at' => '2026-03-28 22:00:00', 'currency' => 'EUR', 'total_price_cents' => 1001]);
    $second = $factory->create(['confirmed_at' => '2026-03-29 20:59:59', 'currency' => 'USD', 'total_price_cents' => 2000]);
    $factory->create(['confirmed_at' => '2026-03-28 21:59:59', 'total_price_cents' => 7000]);
    $factory->create(['confirmed_at' => '2026-03-29 21:00:00', 'total_price_cents' => 8000]);
    $factory->create(['confirmed_at' => '2026-03-29 12:00:00', 'status' => OrderStatus::Cancelled, 'total_price_cents' => 9000]);
    Order::factory()->create(['confirmed_at' => '2026-03-29 12:00:00', 'total_price_cents' => 99000]);
    OrderItem::factory()->for($first)->create(['table_session_guest_id' => null, 'item_name' => 'Renamed', 'item_name_snapshot' => 'ŠALTIBARŠČIAI', 'quantity' => 2, 'total_price_cents' => 1001]);
    OrderItem::factory()->for($second)->create(['table_session_guest_id' => null, 'item_name' => 'Renamed', 'item_name_snapshot' => 'šaltibarščiai', 'quantity' => 3, 'total_price_cents' => 2000]);
    OrderItem::factory()->for($first)->cancelled()->create(['table_session_guest_id' => null, 'item_name' => 'Cancelled', 'quantity' => 100]);
    ManualPayment::factory()->for($branch)->for($session, 'tableSession')->create([
        'service_point_id' => $session->service_point_id, 'paid_at' => '2026-03-29 12:00:00', 'amount_cents' => 500, 'currency' => 'EUR',
    ]);
    ManualPayment::factory()->for($branch)->for($session, 'tableSession')->create([
        'service_point_id' => $session->service_point_id, 'paid_at' => '2026-03-29 21:00:00', 'amount_cents' => 9999, 'currency' => 'EUR',
    ]);
    $branches = collect([$branch]);
    $report = app(BranchReportQuery::class)->handle($branches, BranchReportPeriod::fromSelection($branches));
    expect($first->getRawOriginal('confirmed_at'))->toBe('2026-03-28 22:00:00')
        ->and($report['orders_count'])->toBe(2)
        ->and($report['order_total_cents'])->toBeNull()
        ->and(array_column($report['currency_totals'], 'total_cents', 'currency'))->toBe(['EUR' => 1001, 'USD' => 2000])
        ->and($report['payment_currency_totals'][0]['total_cents'])->toBe(500)
        ->and($report['popular_items'])->toHaveCount(1)
        ->and($report['popular_items'][0]['item_name'])->toBe('ŠALTIBARŠČIAI')
        ->and($report['popular_items'][0]['quantity'])->toBe(5)
        ->and($report['popular_items'][0]['total_cents'])->toBeNull();
});

test('report grouping preserves blank historical fallback Unicode case and stable top five order', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'UTC']);
    $order = Order::factory()->recycle($branch)->create(['total_price_cents' => 1100]);
    $names = [
        ['БОРЩ', null, 2], ['борщ', '', 3], ['борщ', '  ', 4],
        ['ŠALTIBARŠČIAI', 'ŠALTIBARŠČIAI', 3], ['Changed', 'šaltibarščiai', 3],
        ['A', 'A', 1], ['B', 'B', 1], ['C', 'C', 1], ['D', 'D', 1],
    ];
    foreach ($names as [$name, $snapshot, $quantity]) {
        OrderItem::factory()->for($order)->create([
            'table_session_guest_id' => null, 'item_name' => $name,
            'item_name_snapshot' => $snapshot, 'quantity' => $quantity,
            'total_price_cents' => 100,
        ]);
    }
    $branches = collect([$branch]);
    $report = app(BranchReportQuery::class)->handle($branches, BranchReportPeriod::fromSelection($branches));

    expect(array_column($report['popular_items'], 'item_name'))->toBe(['БОРЩ', 'šaltibarščiai', 'A', 'B', 'C'])
        ->and(array_column($report['popular_items'], 'quantity'))->toBe([9, 6, 1, 1, 1])
        ->and(array_column($report['popular_items'], 'total_cents'))->toBe([300, 200, 100, 100, 100]);
});

test('payment only reporting preserves recorded signed amounts independently of confirmed orders', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:00', 'UTC'));
    $branch = Branch::factory()->create(['timezone' => 'UTC']);
    $session = TableSession::factory()->recycle($branch)->active()->create();
    foreach ([['EUR', 750], ['EUR', 250], ['EUR', -100], ['USD', 200]] as [$currency, $amount]) {
        ManualPayment::factory()->for($branch)->for($session, 'tableSession')->create([
            'service_point_id' => $session->service_point_id,
            'paid_at' => now(), 'amount_cents' => $amount, 'currency' => $currency,
        ]);
    }
    $branches = collect([$branch]);
    $report = app(BranchReportQuery::class)->handle($branches, BranchReportPeriod::fromSelection($branches));

    expect($report['orders_count'])->toBe(0)
        ->and($report['order_total_cents'])->toBe(0)
        ->and($report['currency_totals'])->toBe([])
        ->and($report['popular_items'])->toBe([])
        ->and($report['payment_currency_totals'])->toBe([
            ['currency' => 'EUR', 'total_cents' => 900, 'total' => '€9.00', 'payment_count' => 3],
            ['currency' => 'USD', 'total_cents' => 200, 'total' => '$2.00', 'payment_count' => 1],
        ]);
});

test('period scope cannot broaden the current authorized branch set', function (): void {
    $authorized = Branch::factory()->create();
    $other = Branch::factory()->create();
    $period = BranchReportPeriod::fromSelection(collect([$authorized, $other]));

    app(BranchReportQuery::class)->handle(collect([$authorized]), $period);
})->throws(InvalidArgumentException::class);

test('basic report cache crosses branch midnight immediately and rechecks access', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 11:59:59', 'UTC'));
    $branch = Branch::factory()->create(['timezone' => 'Pacific/Auckland']);
    Order::factory()->recycle($branch)->create(['confirmed_at' => now(), 'total_price_cents' => 100]);
    Order::factory()->recycle($branch)->create(['confirmed_at' => '2026-06-04 12:00:00', 'total_price_cents' => 200]);
    $user = User::factory()->create();
    $accessible = collect([$branch->id]);
    $this->mock(ResolveWaiterAccessibleBranchIdsAction::class)->shouldReceive('handle')->andReturnUsing(fn () => $accessible);
    $action = app(BuildBasicAnalyticsDashboardAction::class);
    $before = $action->handle($user)['analytics'];
    $this->travel(1)->seconds();
    $after = $action->handle($user)['analytics'];
    expect($before['orders_today_total'])->toBe('€1.00')
        ->and($after['orders_today_total'])->toBe('€2.00')
        ->and($after['cache_key'])->not->toBe($before['cache_key']);

    $accessible->pop();
    expect($action->handle($user))->toBe(['has_access' => false, 'analytics' => null]);
});

test('historical period leaves active sessions current and rounds currency averages independently', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:00', 'UTC'));
    $branch = Branch::factory()->create(['timezone' => 'UTC']);
    $session = TableSession::factory()->recycle($branch)->active()->create();
    Order::factory()->forTableSession($session)->count(2)->create(['confirmed_at' => '2026-06-03 12:00:00', 'total_price_cents' => 100, 'currency' => 'EUR']);
    Order::factory()->forTableSession($session)->create(['confirmed_at' => '2026-06-03 12:00:00', 'total_price_cents' => 101, 'currency' => 'EUR']);
    $branches = collect([$branch]);
    $report = app(BranchReportQuery::class)->handle($branches, BranchReportPeriod::fromSelection($branches, 'yesterday'));
    expect($report['orders_count'])->toBe(3)
        ->and($report['currency_totals'][0]['average_check'])->toBe('€1.00')
        ->and($report['active_tables_count'])->toBe(1);
});

test('legacy empty order currencies share the existing euro fallback', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'UTC']);
    $session = TableSession::factory()->recycle($branch)->active()->create();
    Order::factory()->forTableSession($session)->create(['currency' => '', 'total_price_cents' => 101]);
    Order::factory()->forTableSession($session)->create(['currency' => 'EUR', 'total_price_cents' => 200]);
    $branches = collect([$branch]);
    $report = app(BranchReportQuery::class)->handle($branches, BranchReportPeriod::fromSelection($branches));

    expect($report['single_currency'])->toBe('EUR')
        ->and($report['order_total_cents'])->toBe(301)
        ->and($report['currency_totals'])->toBe([
            ['currency' => 'EUR', 'total_cents' => 301, 'total' => '€3.01', 'order_count' => 2, 'average_check' => '€1.51'],
        ]);
});

test('all branch reporting applies independent local days to each authorized branch', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 00:30:00', 'UTC'));
    $vilnius = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $newYork = Branch::factory()->create(['timezone' => 'America/New_York']);
    $vilniusSession = TableSession::factory()->recycle($vilnius)->active()->create();
    $newYorkSession = TableSession::factory()->recycle($newYork)->active()->create();
    Order::factory()->forTableSession($vilniusSession)->create(['confirmed_at' => '2026-06-03 21:00:00', 'total_price_cents' => 100]);
    Order::factory()->forTableSession($vilniusSession)->create(['confirmed_at' => '2026-06-03 20:59:59', 'total_price_cents' => 9000]);
    Order::factory()->forTableSession($newYorkSession)->create(['confirmed_at' => '2026-06-03 04:00:00', 'total_price_cents' => 200]);
    Order::factory()->forTableSession($newYorkSession)->create(['confirmed_at' => '2026-06-04 04:00:00', 'total_price_cents' => 9000]);
    $branches = collect([$vilnius, $newYork]);
    $report = app(BranchReportQuery::class)->handle($branches, BranchReportPeriod::fromSelection($branches));

    expect($report['orders_count'])->toBe(2)
        ->and($report['order_total_cents'])->toBe(300);
});

test('report historical name aggregates have a supporting lookup index', function (): void {
    expect(Schema::hasIndex('order_items', ['item_name', 'item_name_snapshot']))->toBeTrue();
});
