<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\User;
use App\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BuildRestaurantDashboardAction
{
    private const CACHE_SECONDS = 60;

    private const CACHE_STORE = 'database';

    private const INDEX_SECONDS = 600;

    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $resolveKitchenDepartments,
        private readonly ResolveBarAccessibleDepartmentIdsAction $resolveBarDepartments,
        private readonly BuildWaiterDashboardAction $buildWaiterDashboard,
    ) {}

    /**
     * @return array{has_access: bool, dashboard: array<string, mixed>|null}
     */
    public function handle(User $user): array
    {
        $access = $this->resolveAccess($user);

        if ($access['dashboard']->isEmpty()) {
            return [
                'has_access' => false,
                'dashboard' => null,
            ];
        }

        $cacheKey = self::cacheKeyForAccess($access);
        $dashboard = self::cache()->remember(
            $cacheKey,
            self::CACHE_SECONDS,
            fn (): array => $this->buildDashboard($user, $access, $cacheKey),
        );

        $this->rememberBranchCacheKeys($access['dashboard'], $cacheKey);

        return [
            'has_access' => true,
            'dashboard' => $dashboard,
        ];
    }

    public function userHasAccess(User $user): bool
    {
        return $this->resolveAccess($user)['dashboard']->isNotEmpty();
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

    public static function cacheStore(): string
    {
        return self::CACHE_STORE;
    }

    /**
     * @param  array<string, Collection<int, covariant int>>  $access
     */
    public static function cacheKeyForAccess(array $access, ?CarbonImmutable $date = null): string
    {
        $date ??= CarbonImmutable::now();
        $signature = collect($access)
            ->mapWithKeys(fn (Collection $branchIds, string $key): array => [
                $key => $branchIds
                    ->map(fn (mixed $branchId): int => (int) $branchId)
                    ->filter(fn (int $branchId): bool => $branchId > 0)
                    ->unique()
                    ->sort()
                    ->values()
                    ->implode(','),
            ])
            ->sortKeys()
            ->map(fn (string $branchIds, string $key): string => $key.':'.$branchIds)
            ->implode('|');

        return 'restaurant-dashboard:'.sha1($signature).':today:'.$date->toDateString();
    }

    private static function cache(): CacheRepository
    {
        return Cache::store(self::CACHE_STORE);
    }

    private static function branchCacheKeysKey(int $branchId): string
    {
        return 'restaurant-dashboard:branch:'.$branchId.':keys';
    }

    /**
     * @return array<string, Collection<int, int<1, max>>>
     */
    private function resolveAccess(User $user): array
    {
        $reportBranchIds = $this->branchIdsForPermission($user, SystemPermission::ViewReports);
        $viewOrderBranchIds = $this->branchIdsForPermission($user, SystemPermission::ViewOrders);
        $confirmOrderBranchIds = $this->branchIdsForPermission($user, SystemPermission::ConfirmOrders);
        $menuBranchIds = $this->branchIdsForPermission($user, SystemPermission::ManageMenu);
        $servicePointBranchIds = $this->branchIdsForPermission($user, SystemPermission::ManageServicePoints)
            ->merge($viewOrderBranchIds)
            ->merge($confirmOrderBranchIds);
        $qrBranchIds = $this->branchIdsForPermission($user, SystemPermission::GenerateQr);
        $kitchenBranchIds = $this->branchIdsForDepartments($this->resolveKitchenDepartments->handle($user));
        $barBranchIds = $this->branchIdsForDepartments($this->resolveBarDepartments->handle($user));
        $orderBranchIds = $viewOrderBranchIds
            ->merge($confirmOrderBranchIds)
            ->unique()
            ->values();
        $operationsBranchIds = $orderBranchIds
            ->merge($kitchenBranchIds)
            ->merge($barBranchIds)
            ->merge($reportBranchIds)
            ->unique()
            ->sort()
            ->values();
        $dashboardBranchIds = $operationsBranchIds
            ->merge($menuBranchIds)
            ->merge($servicePointBranchIds)
            ->merge($qrBranchIds)
            ->unique()
            ->sort()
            ->values();

        return [
            'dashboard' => $dashboardBranchIds,
            'operations' => $operationsBranchIds,
            'orders' => $orderBranchIds,
            'reports' => $reportBranchIds,
            'menu' => $menuBranchIds,
            'service_points' => $servicePointBranchIds,
            'qr' => $qrBranchIds,
            'kitchen' => $kitchenBranchIds,
            'bar' => $barBranchIds,
        ];
    }

    /**
     * @param  array<string, Collection<int, covariant int>>  $access
     * @return array<string, mixed>
     */
    private function buildDashboard(User $user, array $access, string $cacheKey): array
    {
        $now = CarbonImmutable::now();
        $periodStart = $now->startOfDay();
        $periodEnd = $now->endOfDay();
        $branches = $this->branches($access['dashboard']);
        $defaultCurrency = $this->defaultCurrency($branches);
        $reportOrders = $this->todayOrders($access['reports'], $periodStart, $periodEnd);
        $reportOrderIds = $reportOrders->pluck('id');
        $currencyTotals = $this->currencyTotals($reportOrders);
        $totalOrderCents = $currencyTotals->sum(fn (array $currencyTotal): int => (int) $currencyTotal['total_cents']);
        $singleCurrency = $this->singleCurrency($currencyTotals, $defaultCurrency);
        $canViewReports = $access['reports']->isNotEmpty();

        return [
            'cache_key' => $cacheKey,
            'cached_at' => $now->format('Y-m-d H:i:s'),
            'period_label' => $periodStart->toDateString(),
            'branch_count' => $branches->count(),
            'branch_names' => $branches->pluck('name')->values()->all(),
            'can_view_reports' => $canViewReports,
            'metrics' => [
                'active_tables_count' => $this->activeTablesCount($access['operations']),
                'new_orders_to_waiter_count' => $this->newOrdersToWaiterCount($access['orders']->merge($access['reports'])->unique()->values()),
                'cooking_orders_count' => $this->cookingOrdersCount($access['operations']),
                'ready_positions_count' => $this->readyPositionsCount($access['operations']),
                'orders_today_total' => $canViewReports && $singleCurrency !== null
                    ? $this->formatCents($totalOrderCents).' '.$singleCurrency
                    : null,
                'orders_today_count' => $canViewReports ? $reportOrders->count() : null,
            ],
            'popular_items' => $canViewReports ? $this->popularItems($reportOrderIds, $singleCurrency) : [],
            'quick_actions' => $this->quickActions($user, $access),
        ];
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    private function branchIdsForPermission(User $user, SystemPermission $permission): Collection
    {
        return $this->resolveAccessibleBranchIds
            ->handle($user, $permission)
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  Collection<int, covariant int>  $departmentIds
     * @return Collection<int, int<1, max>>
     */
    private function branchIdsForDepartments(Collection $departmentIds): Collection
    {
        if ($departmentIds->isEmpty()) {
            return collect();
        }

        return KitchenDepartment::query()
            ->select(['id', 'branch_id'])
            ->whereIn('id', $departmentIds)
            ->orderBy('branch_id')
            ->pluck('branch_id')
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     * @return Collection<int, Branch>
     */
    private function branches(Collection $branchIds): Collection
    {
        if ($branchIds->isEmpty()) {
            return collect();
        }

        return Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'currency'])
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     * @return Collection<int, Order>
     */
    private function todayOrders(Collection $branchIds, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Collection
    {
        if ($branchIds->isEmpty()) {
            return collect();
        }

        return Order::query()
            ->select(['id', 'branch_id', 'status', 'confirmed_at', 'total_price_cents', 'currency'])
            ->whereIn('branch_id', $branchIds)
            ->whereNotIn('status', [OrderStatus::Cancelled->value])
            ->whereBetween('confirmed_at', [$periodStart, $periodEnd])
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    private function activeTablesCount(Collection $branchIds): int
    {
        if ($branchIds->isEmpty()) {
            return 0;
        }

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
    private function newOrdersToWaiterCount(Collection $branchIds): int
    {
        if ($branchIds->isEmpty()) {
            return 0;
        }

        return DraftOrder::query()
            ->where('status', DraftOrderStatus::SentToWaiter->value)
            ->whereHas('tableSession', function ($query) use ($branchIds): void {
                $query->whereIn('branch_id', $branchIds);
            })
            ->count();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    private function cookingOrdersCount(Collection $branchIds): int
    {
        if ($branchIds->isEmpty()) {
            return 0;
        }

        return Order::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', [
                OrderStatus::SentToKitchenBar->value,
                OrderStatus::InProgress->value,
            ])
            ->count();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    private function readyPositionsCount(Collection $branchIds): int
    {
        if ($branchIds->isEmpty()) {
            return 0;
        }

        return KitchenTicketItem::query()
            ->where('status', KitchenTicketItemStatus::Ready->value)
            ->whereNull('served_at')
            ->whereHas('kitchenTicket', function ($query) use ($branchIds): void {
                $query->whereIn('branch_id', $branchIds);
            })
            ->count();
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
                $totalCents = (int) $currencyOrders->sum('total_price_cents');

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
            ->select(['id', 'order_id', 'item_name', 'item_name_snapshot', 'quantity', 'total_price_cents'])
            ->whereIn('order_id', $orderIds)
            ->active()
            ->orderBy('item_name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (OrderItem $item): string => mb_strtolower($item->historicalItemName()))
            ->map(function (Collection $items): array {
                $firstItem = $items->first();
                $totalCents = (int) $items->sum('total_price_cents');

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
     * @param  array<string, Collection<int, covariant int>>  $access
     * @return list<array{label: string, description: string, icon: string, href: string|null, is_available: bool}>
     */
    private function quickActions(User $user, array $access): array
    {
        return [
            $this->branchQuickAction(
                label: 'Menu',
                description: 'Manage branch menu',
                icon: 'book-open',
                routeName: 'organizations.brands.branches.menu.index',
                branchIds: $access['menu'],
            ),
            $this->branchQuickAction(
                label: 'Tables',
                description: 'Open tables and manage service points',
                icon: 'squares-2x2',
                routeName: 'organizations.brands.branches.service-points.index',
                branchIds: $access['service_points'],
            ),
            $this->branchQuickAction(
                label: 'QR',
                description: 'Print permanent branch QR codes',
                icon: 'qr-code',
                routeName: 'organizations.brands.branches.qr.print',
                branchIds: $access['qr'],
            ),
            $this->screenQuickAction(
                label: 'QR lookup',
                description: 'Find a printed QR sticker',
                icon: 'magnifying-glass',
                routeName: 'restaurant.qr-lookup.index',
                isAvailable: $access['qr']->isNotEmpty(),
            ),
            $this->screenQuickAction(
                label: 'Waiter screen',
                description: 'Open live waiter workspace',
                icon: 'clipboard-document-list',
                routeName: 'restaurant.waiter.dashboard',
                isAvailable: $this->buildWaiterDashboard->userHasAccess($user),
            ),
            $this->screenQuickAction(
                label: 'Kitchen',
                description: 'Open kitchen tickets',
                icon: 'fire',
                routeName: 'restaurant.kitchen.dashboard',
                isAvailable: $access['kitchen']->isNotEmpty(),
            ),
            $this->screenQuickAction(
                label: 'reports.title',
                description: 'reports.quick_actions.view_cached_branch_analytics',
                icon: 'chart-bar',
                routeName: 'restaurant.dashboard',
                isAvailable: $access['reports']->isNotEmpty(),
            ),
        ];
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     * @return array{label: string, description: string, icon: string, href: string|null, is_available: bool}
     */
    private function branchQuickAction(string $label, string $description, string $icon, string $routeName, Collection $branchIds): array
    {
        $branch = $this->firstBranch($branchIds);

        return [
            'label' => $label,
            'description' => $description,
            'icon' => $icon,
            'href' => $branch instanceof Branch ? route($routeName, [
                'organization' => $branch->organization_id,
                'brand' => $branch->brand_id,
                'branch' => $branch->id,
            ]) : null,
            'is_available' => $branch instanceof Branch,
        ];
    }

    /**
     * @return array{label: string, description: string, icon: string, href: string|null, is_available: bool}
     */
    private function screenQuickAction(string $label, string $description, string $icon, string $routeName, bool $isAvailable): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'icon' => $icon,
            'href' => $isAvailable ? route($routeName) : null,
            'is_available' => $isAvailable,
        ];
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    private function firstBranch(Collection $branchIds): ?Branch
    {
        if ($branchIds->isEmpty()) {
            return null;
        }

        return Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name'])
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->first();
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

    private function formatCents(int $cents): string
    {
        return MoneyFormatter::centsToDecimal($cents);
    }
}
