<?php

namespace App\Actions\Analytics;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrderStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BuildBasicAnalyticsDashboardAction
{
    private const CACHE_SECONDS = 300;

    private const CACHE_STORE = 'database';

    private const INDEX_SECONDS = 600;

    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    /**
     * @return array{has_access: bool, analytics: array<string, mixed>|null}
     */
    public function handle(User $user): array
    {
        $branchIds = $this->accessibleBranchIds($user);

        if ($branchIds->isEmpty()) {
            return [
                'has_access' => false,
                'analytics' => null,
            ];
        }

        $cacheKey = self::cacheKeyForBranchIds($branchIds);
        $analytics = self::cache()->remember(
            $cacheKey,
            self::CACHE_SECONDS,
            fn (): array => $this->buildAnalytics($branchIds, $cacheKey),
        );

        $this->rememberBranchCacheKeys($branchIds, $cacheKey);

        return [
            'has_access' => true,
            'analytics' => $analytics,
        ];
    }

    public static function forgetForBranch(int $branchId): void
    {
        if ($branchId < 1) {
            return;
        }

        $cache = self::cache();
        $indexKey = self::branchCacheKeysKey($branchId);
        $cacheKeys = $cache->get($indexKey, []);

        if (is_array($cacheKeys)) {
            foreach ($cacheKeys as $cacheKey) {
                if (is_string($cacheKey) && $cacheKey !== '') {
                    $cache->forget($cacheKey);
                }
            }
        }

        $cache->forget($indexKey);
    }

    /**
     * @param  iterable<int, int>  $branchIds
     */
    public static function forgetForBranches(iterable $branchIds): void
    {
        foreach ($branchIds as $branchId) {
            self::forgetForBranch((int) $branchId);
        }
    }

    public static function cacheStore(): string
    {
        return self::CACHE_STORE;
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    public static function cacheKeyForBranchIds(Collection $branchIds, ?CarbonImmutable $date = null): string
    {
        $normalizedBranchIds = $branchIds
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->sort()
            ->values();

        $date ??= CarbonImmutable::now();

        return 'analytics:dashboard:branches:'
            .sha1($normalizedBranchIds->implode(','))
            .':today:'.$date->toDateString();
    }

    private static function cache(): CacheRepository
    {
        return Cache::store(self::CACHE_STORE);
    }

    private static function branchCacheKeysKey(int $branchId): string
    {
        return 'analytics:dashboard:branch:'.$branchId.':keys';
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    private function accessibleBranchIds(User $user): Collection
    {
        return $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ViewReports)
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     * @return array<string, mixed>
     */
    private function buildAnalytics(Collection $branchIds, string $cacheKey): array
    {
        $now = CarbonImmutable::now();
        $periodStart = $now->startOfDay();
        $periodEnd = $now->endOfDay();
        $branches = Branch::query()
            ->select(['id', 'name', 'currency'])
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $todayOrders = $this->todayOrders($branchIds, $periodStart, $periodEnd);
        $todayOrderIds = $todayOrders->pluck('id');
        $currencyTotals = $this->currencyTotals($todayOrders);
        $ordersTodayCount = $todayOrders->count();
        $totalOrderCents = $currencyTotals->sum(fn (array $currencyTotal): int => (int) $currencyTotal['total_cents']);
        $defaultCurrency = $this->defaultCurrency($branches);
        $singleCurrency = $this->singleCurrency($currencyTotals, $defaultCurrency);

        return [
            'cache_key' => $cacheKey,
            'cached_at' => $now->format('Y-m-d H:i:s'),
            'period_label' => $periodStart->toDateString(),
            'branch_count' => $branches->count(),
            'branch_names' => $branches
                ->pluck('name')
                ->values()
                ->all(),
            'orders_today_count' => $ordersTodayCount,
            'orders_today_total' => $singleCurrency !== null
                ? $this->formatCents($totalOrderCents).' '.$singleCurrency
                : __('ui.actions.analytics.buildbasicanalyticsdashboardaction.multiple_currencies'),
            'average_check' => $singleCurrency !== null && $ordersTodayCount > 0
                ? $this->formatCents((int) round($totalOrderCents / $ordersTodayCount)).' '.$singleCurrency
                : ($ordersTodayCount > 0 ? __('ui.actions.analytics.buildbasicanalyticsdashboardaction.multiple_currencies') : $this->formatCents(0).' '.$defaultCurrency),
            'currency_totals' => $currencyTotals->values()->all(),
            'popular_items' => $this->popularItems($todayOrderIds, $singleCurrency),
            'active_tables_count' => $this->activeTablesCount($branchIds),
            'closed_sessions_count' => $this->closedSessionsCount($branchIds, $periodStart, $periodEnd),
            'cancelled_orders_count' => $this->cancelledOrdersCount($branchIds, $periodStart, $periodEnd),
        ];
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     * @return Collection<int, Order>
     */
    private function todayOrders(Collection $branchIds, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Collection
    {
        return Order::query()
            ->select(['id', 'branch_id', 'status', 'confirmed_at', 'total_price', 'currency'])
            ->whereIn('branch_id', $branchIds)
            ->whereNotIn('status', [OrderStatus::Cancelled->value])
            ->whereBetween('confirmed_at', [$periodStart, $periodEnd])
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{currency: string, total_cents: int, total: string}>
     */
    private function currencyTotals(Collection $orders): Collection
    {
        return $orders
            ->groupBy(fn (Order $order): string => $order->currency ?: 'EUR')
            ->map(function (Collection $currencyOrders, string $currency): array {
                $totalCents = $currencyOrders->sum(
                    fn (Order $order): int => $this->decimalToCents($order->total_price),
                );

                return [
                    'currency' => $currency,
                    'total_cents' => $totalCents,
                    'total' => $this->formatCents($totalCents).' '.$currency,
                ];
            })
            ->sortKeys()
            ->values();
    }

    /**
     * @param  Collection<int, covariant int>  $orderIds
     * @return list<array{item_name: string, quantity: int, total: string}>
     */
    private function popularItems(Collection $orderIds, ?string $singleCurrency): array
    {
        if ($orderIds->isEmpty()) {
            return [];
        }

        return OrderItem::query()
            ->select(['id', 'order_id', 'item_name', 'item_name_snapshot', 'quantity', 'total_price'])
            ->whereIn('order_id', $orderIds)
            ->orderBy('item_name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (OrderItem $item): string => mb_strtolower($item->historicalItemName()))
            ->map(function (Collection $items): array {
                $firstItem = $items->first();
                $totalCents = $items->sum(fn (OrderItem $item): int => $this->decimalToCents($item->total_price));

                return [
                    'item_name' => $firstItem instanceof OrderItem ? $firstItem->historicalItemName() : __('ui.actions.analytics.buildbasicanalyticsdashboardaction.dish'),
                    'quantity' => $items->sum(fn (OrderItem $item): int => (int) $item->quantity),
                    'total_cents' => $totalCents,
                ];
            })
            ->sortByDesc('quantity')
            ->take(5)
            ->map(fn (array $item): array => [
                'item_name' => $item['item_name'],
                'quantity' => (int) $item['quantity'],
                'total' => $singleCurrency !== null
                    ? $this->formatCents((int) $item['total_cents']).' '.$singleCurrency
                    : __('ui.actions.analytics.buildbasicanalyticsdashboardaction.mixed'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    private function activeTablesCount(Collection $branchIds): int
    {
        return TableSession::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', [
                TableSessionStatus::Pending->value,
                TableSessionStatus::Active->value,
                TableSessionStatus::WaitingWaiterConfirmation->value,
                TableSessionStatus::PaymentRequested->value,
            ])
            ->count();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    private function closedSessionsCount(Collection $branchIds, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): int
    {
        return TableSession::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', TableSessionStatus::Closed->value)
            ->whereBetween('ended_at', [$periodStart, $periodEnd])
            ->count();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    private function cancelledOrdersCount(Collection $branchIds, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): int
    {
        return Order::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', OrderStatus::Cancelled->value)
            ->whereBetween('updated_at', [$periodStart, $periodEnd])
            ->count();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    private function rememberBranchCacheKeys(Collection $branchIds, string $cacheKey): void
    {
        $cache = self::cache();

        $branchIds->each(function (int $branchId) use ($cache, $cacheKey): void {
            $indexKey = self::branchCacheKeysKey($branchId);
            $cacheKeys = $cache->get($indexKey, []);
            $cacheKeys = is_array($cacheKeys) ? $cacheKeys : [];
            $cacheKeys[] = $cacheKey;

            $cache->put(
                $indexKey,
                collect($cacheKeys)
                    ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
                    ->unique()
                    ->take(-50)
                    ->values()
                    ->all(),
                self::INDEX_SECONDS,
            );
        });
    }

    private function decimalToCents(string|int|float|null $amount): int
    {
        $normalized = number_format((float) ($amount ?? 0), 2, '.', '');
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = explode('.', $normalized);
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = abs($cents);
        $formatted = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }

    /**
     * @param  Collection<int, Branch>  $branches
     */
    private function defaultCurrency(Collection $branches): string
    {
        $currency = $branches
            ->pluck('currency')
            ->filter(fn (mixed $currency): bool => is_string($currency) && $currency !== '')
            ->first();

        return $currency ?? 'EUR';
    }

    /**
     * @param  Collection<int, array{currency: string, total_cents: int, total: string}>  $currencyTotals
     */
    private function singleCurrency(Collection $currencyTotals, string $defaultCurrency): ?string
    {
        if ($currencyTotals->isEmpty()) {
            return $defaultCurrency;
        }

        if ($currencyTotals->count() === 1) {
            return (string) $currencyTotals->first()['currency'];
        }

        return null;
    }
}
