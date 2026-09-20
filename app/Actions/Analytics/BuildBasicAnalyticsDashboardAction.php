<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\User;
use App\Services\Reports\BranchReportQuery;
use App\Support\BranchReportCacheVersion;
use App\Support\DisplayPreferences;
use App\Support\LocalizedDateFormatter;
use App\Support\MoneyFormatter;
use App\Support\Reports\BranchReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Localizable;
use LogicException;

class BuildBasicAnalyticsDashboardAction
{
    use Localizable;

    private const CACHE_FRESH_SECONDS = 240;

    private const CACHE_SECONDS = 300;

    private const CACHE_STORE = 'database';

    private const INDEX_SECONDS = 600;

    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly BranchReportQuery $reportQuery,
    ) {}

    /**
     * @return array{has_access: bool, analytics: array<string, mixed>|null}
     */
    public function handle(User $user, string $preset = 'today', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $branchIds = $this->accessibleBranchIds($user);

        if ($branchIds->isEmpty()) {
            return [
                'has_access' => false,
                'analytics' => null,
            ];
        }

        $branches = Branch::query()
            ->select(['id', 'name', 'currency', 'timezone'])
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $period = BranchReportPeriod::fromSelection($branches, $preset, $dateFrom, $dateTo);
        $cache = self::cache();
        $preferences = DisplayPreferences::forUser($user);
        $cacheKey = self::cacheKeyForBranchIds($branchIds, preferences: $preferences).':period:'.$period->fingerprint()
            .':generation:'.BranchReportCacheVersion::fingerprint($cache, 'analytics', $branchIds);
        $locale = App::currentLocale();
        $analytics = $cache->flexible(
            $cacheKey,
            [self::CACHE_FRESH_SECONDS, self::CACHE_SECONDS],
            fn (): array => $this->withLocale($locale, fn (): array => $this->buildAnalytics($branches, $period, $cacheKey, $preferences)),
            lock: ['seconds' => 30],
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
        BranchReportCacheVersion::invalidate($cache, 'analytics', $branchId);
        $indexKey = self::branchCacheKeysKey($branchId);
        $cacheKeys = $cache->get($indexKey, []);

        if (is_array($cacheKeys)) {
            foreach ($cacheKeys as $cacheKey) {
                if (is_string($cacheKey) && $cacheKey !== '') {
                    $cache->forget(CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX.$cacheKey);
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
    public static function cacheKeyForBranchIds(Collection $branchIds, ?CarbonImmutable $date = null, ?DisplayPreferences $preferences = null): string
    {
        $normalizedBranchIds = $branchIds
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->sort()
            ->values();

        $date ??= CarbonImmutable::now();

        return 'analytics:dashboard:v5:branches:'
            .sha1($normalizedBranchIds->implode(','))
            .':today:'.$date->toDateString()
            .':locale:'.App::currentLocale().':formats:'.($preferences ?? DisplayPreferences::current())->fingerprint();
    }

    private static function cache(): CacheRepository
    {
        $cache = Cache::store(self::CACHE_STORE);

        if (! $cache instanceof CacheRepository) {
            throw new LogicException('Report snapshots require the Laravel cache repository.');
        }

        return $cache;
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
     * @param  Collection<int, Branch>  $branches
     * @return array<string, mixed>
     */
    private function buildAnalytics(Collection $branches, BranchReportPeriod $period, string $cacheKey, DisplayPreferences $preferences): array
    {
        (new PruneExpiredReportCacheEntriesAction)->handle(self::cache());

        $report = $this->reportQuery->handle($branches, $period, $preferences);
        $singleCurrency = $report['single_currency'];
        $ordersCount = $report['orders_count'];
        $totalCents = $report['order_total_cents'];

        return [
            'cache_key' => $cacheKey,
            'cached_at' => LocalizedDateFormatter::dateTime(CarbonImmutable::now(), $preferences),
            'period_label' => $period->label($preferences),
            'branch_count' => $branches->count(),
            'branch_names' => $branches->pluck('name')->values()->all(),
            'orders_today_count' => $ordersCount,
            'orders_today_total' => $singleCurrency !== null
                ? MoneyFormatter::formatCents((int) $totalCents, $singleCurrency, $preferences)
                : __('ui.actions.analytics.buildbasicanalyticsdashboardaction.multiple_currencies'),
            'average_check' => $singleCurrency !== null && $ordersCount > 0
                ? MoneyFormatter::formatCents(MoneyFormatter::roundedDivide((int) $totalCents, $ordersCount), $singleCurrency, $preferences)
                : ($ordersCount > 0 ? __('ui.actions.analytics.buildbasicanalyticsdashboardaction.multiple_currencies') : MoneyFormatter::formatCents(0, $report['default_currency'], $preferences)),
            'currency_totals' => $report['currency_totals'],
            'payment_currency_totals' => $report['payment_currency_totals'],
            'popular_items' => $report['popular_items'],
            'active_tables_count' => $report['active_tables_count'],
            'closed_sessions_count' => $report['closed_sessions_count'],
            'cancelled_orders_count' => $report['cancelled_orders_count'],
        ];
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
            $cacheKeys = collect(is_array($cacheKeys) ? $cacheKeys : [])
                ->push($cacheKey)
                ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
                ->unique()
                ->values();
            $retainedKeys = $cacheKeys->take(-50);

            foreach ($cacheKeys->diff($retainedKeys) as $displacedKey) {
                $cache->forget(CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX.$displacedKey);
                $cache->forget($displacedKey);
            }

            $cache->put($indexKey, $retainedKeys->values()->all(), self::INDEX_SECONDS);
        });
    }
}
