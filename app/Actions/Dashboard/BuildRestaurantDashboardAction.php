<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Analytics\PruneExpiredReportCacheEntriesAction;
use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
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
use App\Models\TableSession;
use App\Models\User;
use App\Services\Reports\BranchReportQuery;
use App\Services\Restaurant\BranchReadinessService;
use App\Services\Waiter\WaiterTableQueryService;
use App\Support\BranchReportCacheVersion;
use App\Support\LocalizedDateFormatter;
use App\Support\Reports\BranchReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Localizable;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class BuildRestaurantDashboardAction
{
    use Localizable;

    private const CACHE_FRESH_SECONDS = 45;

    private const CACHE_SECONDS = 60;

    private const CACHE_STORE = 'database';

    private const INDEX_SECONDS = 600;

    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $resolveKitchenDepartments,
        private readonly ResolveBarAccessibleDepartmentIdsAction $resolveBarDepartments,
        private readonly BranchReportQuery $reportQuery,
        private readonly BranchReadinessService $readiness,
        private readonly WaiterTableQueryService $waiterTables,
        private readonly PruneExpiredReportCacheEntriesAction $pruneReportCache,
    ) {}

    /**
     * @return array{has_access: bool, dashboard: array<string, mixed>|null}
     */
    public function handle(User $user, mixed $branchId = '', string $preset = 'today', ?string $from = null, ?string $to = null): array
    {
        $context = $this->context($user, $branchId);
        if ($context['access']['dashboard']->isEmpty()) {
            return ['has_access' => false, 'dashboard' => null];
        }
        $dashboard = $this->buildDashboard($user, $context, $preset, $from, $to);

        return ['has_access' => true, 'dashboard' => $dashboard, 'report_snapshot' => $dashboard['report_snapshot']];
    }

    /** @return array{access: array<string, Collection<int, int>>, branches: Collection<int, Branch>, selected: Branch|null} */
    public function context(User $user, mixed $selection = ''): array
    {
        $id = self::validatedBranchId($selection);
        $access = $this->resolveAccess($user->fresh() ?? $user);
        $branches = $this->branches($access['dashboard'], $user);
        $access = array_map(fn (Collection $ids): Collection => $ids->intersect($branches->pluck('id'))->values(), $access);
        if ($id !== null && ! $access['dashboard']->contains($id)) {
            throw ValidationException::withMessages(['selectedBranchId' => __('dashboard.control.invalid_branch')]);
        }
        $selected = $id === null ? ($branches->count() === 1 ? $branches->first() : null) : $branches->firstWhere('id', $id);
        if ($selected instanceof Branch) {
            $access = array_map(fn (Collection $ids): Collection => $ids->intersect([$selected->id])->values(), $access);
        }

        return ['access' => $access, 'branches' => $branches, 'selected' => $selected];
    }

    public static function validatedBranchId(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^[1-9][0-9]*$/D', (string) $value)
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw ValidationException::withMessages(['selectedBranchId' => __('dashboard.control.invalid_branch')]);
        }

        return (int) $value;
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
        BranchReportCacheVersion::invalidate($cache, 'dashboard', $branchId);
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

        return 'restaurant-dashboard:v5:'.sha1($signature)
            .':today:'.$date->toDateString()
            .':locale:'.App::currentLocale();
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
        return 'restaurant-dashboard:branch:'.$branchId.':keys';
    }

    /**
     * @return array<string, Collection<int, int>>
     */
    private function resolveAccess(User $user): array
    {
        $permissions = $this->resolveAccessibleBranchIds->handleMany($user, [SystemPermission::ViewReports, SystemPermission::ViewOrders, SystemPermission::ConfirmOrders, SystemPermission::ManageMenu, SystemPermission::ManageServicePoints, SystemPermission::GenerateQr]);
        $reportBranchIds = $permissions[SystemPermission::ViewReports->value];
        $viewOrderBranchIds = $permissions[SystemPermission::ViewOrders->value];
        $confirmOrderBranchIds = $permissions[SystemPermission::ConfirmOrders->value];
        $menuBranchIds = $permissions[SystemPermission::ManageMenu->value];
        $servicePointBranchIds = $permissions[SystemPermission::ManageServicePoints->value]
            ->merge($viewOrderBranchIds)
            ->merge($confirmOrderBranchIds);
        $qrBranchIds = $permissions[SystemPermission::GenerateQr->value];
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
            'waiter' => $viewOrderBranchIds,
            'reports' => $reportBranchIds,
            'menu' => $menuBranchIds,
            'service_points' => $servicePointBranchIds,
            'qr' => $qrBranchIds,
            'kitchen' => $kitchenBranchIds,
            'bar' => $barBranchIds,
        ];
    }

    /**
     * @param  array{access:array<string,Collection<int,int>>,branches:Collection<int,Branch>,selected:Branch|null}  $context
     * @return array<string, mixed>
     */
    private function buildDashboard(User $user, array $context, string $preset, ?string $from, ?string $to): array
    {
        $access = $context['access'];
        $selected = $context['selected'];
        $branches = $context['branches']->whereIn('id', $access['dashboard'])->values();
        $reportBranches = $branches->whereIn('id', $access['reports'])->values();
        $period = BranchReportPeriod::fromSelection($branches, $preset, $from, $to);
        $cacheKey = self::cacheKeyForAccess($access).':period:'.$period->fingerprint();
        $reportPeriod = BranchReportPeriod::fromSelection($reportBranches, $preset, $from, $to);
        $reportKey = $cacheKey.':generation:'.BranchReportCacheVersion::fingerprint(self::cache(), 'dashboard', $access['dashboard']);
        $canViewReports = $reportBranches->isNotEmpty();
        $report = null;
        $stale = false;
        $locale = App::currentLocale();
        if ($canViewReports) {
            try {
                $report = self::cache()->flexible($reportKey, [self::CACHE_FRESH_SECONDS, self::CACHE_SECONDS], function () use ($reportBranches, $reportPeriod, $reportKey, $locale): array {
                    $this->pruneReportCache->handle(self::cache());

                    return $this->withLocale($locale, fn (): array => [...$this->reportQuery->handle($reportBranches, $reportPeriod), 'cache_key' => $reportKey, 'generated_at' => CarbonImmutable::now()->toIso8601String(), 'cached_at' => LocalizedDateFormatter::dateTime(CarbonImmutable::now())]);
                }, lock: ['seconds' => 30]);
                self::cache()->put($cacheKey.':last-success', $report, 600);
                $this->rememberBranchCacheKeys($access['dashboard'], $reportKey);
            } catch (Throwable $exception) {
                report($exception);
                $report = self::cache()->get($cacheKey.':last-success');
                $stale = true;
            }
        }
        $reportSnapshot = $report;
        $operationsKey = self::cacheKeyForAccess($access).':operations:actor:'.$user->id;
        $operationsStale = false;
        try {
            $operationSnapshot = ['items' => $this->operationCards($user, $branches, $access, $selected), 'at' => CarbonImmutable::now()->toIso8601String()];
            self::cache()->put($operationsKey, $operationSnapshot, 120);
        } catch (Throwable $exception) {
            report($exception);
            $operationSnapshot = self::cache()->get($operationsKey, ['items' => [], 'at' => null]);
            $operationsStale = true;
        }
        $operations = $operationSnapshot['items'];
        if (is_array($report) && isset($report['generated_at'])) {
            $report['cached_at'] = LocalizedDateFormatter::dateTime(CarbonImmutable::parse($report['generated_at'])->setTimezone($selected?->timezone ?: 'UTC'));
        }
        $readiness = $selected instanceof Branch ? $this->readiness->handle($user, $selected) : null;
        $quickActions = $this->quickActions($access, $selected);
        foreach ($quickActions as &$action) {
            if ($action['label'] === 'reports.title' && $action['is_available']) {
                $action['href'] = route('restaurant.dashboard', [
                    'branch' => $selected?->id,
                    'period' => $preset === 'custom' ? 'custom:'.$from.':'.$to : $preset,
                ]).'#reports';
            }
        }
        unset($action);
        $mainLinks = array_map(fn (array $action): array => [
            'key' => $action['label'], 'label' => match ($action['label']) {
                'Menu' => __('navigation.menu'),
                'Tables' => __('reports.exports.tables'),
                'QR' => __('qr.labels.qr'),
                'QR lookup' => __('qr.lookup.title'),
                'Waiter screen' => __('navigation.waiter'),
                'Kitchen' => __('navigation.kitchen'),
                default => __($action['label']),
            }, 'icon' => $action['icon'], 'href' => $action['href'],
            'requires_branch' => $action['requires_branch'], 'is_available' => $action['is_available'],
        ], $quickActions);
        $popularItems = $report['popular_items'] ?? [];
        $metrics = [
            'active_tables_count' => $this->activeTablesCount($access['operations']),
            'new_orders_to_waiter_count' => $this->newOrdersToWaiterCount($access['orders']->merge($access['reports'])->unique()->values()),
            'cooking_orders_count' => $this->cookingOrdersCount($access['operations']),
            'ready_positions_count' => $this->readyPositionsCount($access['operations']),
            'orders_today_total' => ($report['order_total_cents'] ?? null) !== null && count($report['currency_totals'] ?? []) === 1 ? $report['currency_totals'][0]['total'] : null,
            'orders_today_count' => $report['orders_count'] ?? null,
        ];

        return [
            'report_snapshot' => $reportSnapshot, 'cache_key' => $reportKey, 'cached_at' => $report['cached_at'] ?? null, 'period_label' => $period->label(),
            'branch_count' => $branches->count(), 'branch_names' => $branches->pluck('name')->all(),
            'branches' => $context['branches']->map(fn (Branch $branch): array => $this->branchPresentation($branch))->all(),
            'selected_branch' => $selected instanceof Branch ? $this->branchPresentation($selected) : null,
            'can_view_reports' => $canViewReports, 'metrics' => $metrics, 'popular_items' => $popularItems,
            'quick_actions' => $quickActions, 'main_links' => $mainLinks, 'operations' => $operations,
            'operations_updated_at' => ($operationSnapshot['at'] === null ? '—' : LocalizedDateFormatter::dateTime(CarbonImmutable::parse($operationSnapshot['at'])->setTimezone($selected?->timezone ?: 'UTC'))),
            'operations_stale' => $operationsStale, 'readiness' => $readiness, 'ordering' => $readiness['ordering'] ?? null,
            'report' => [
                'can_view_reports' => $canViewReports, 'period_label' => $period->label(), 'cached_at' => $report['cached_at'] ?? null,
                'stale' => $stale, 'unavailable' => $canViewReports && $report === null,
                'metrics' => $this->reportCards($report), 'popular_items' => array_map(fn (array $item): array => ['key' => $item['item_name'], ...$item], $popularItems),
                'empty' => $report !== null && $report['orders_count'] === 0,
            ],
        ];
    }

    /** @return array{id:int,label:string,organization_name:string,brand_name:string,name:string,timezone:string} */
    private function branchPresentation(Branch $branch): array
    {
        return ['id' => $branch->id, 'label' => $branch->organization->name.' → '.$branch->brand->name.' → '.$branch->name,
            'organization_name' => $branch->organization->name, 'brand_name' => $branch->brand->name, 'name' => $branch->name, 'timezone' => $branch->timezone];
    }

    /** @param array<string,mixed>|null $report @return list<array<string,mixed>> */
    private function reportCards(?array $report): array
    {
        if ($report === null) {
            return [];
        }
        $cards = [['key' => 'orders', 'label' => __('dashboard.control.confirmed_orders'), 'value' => $report['orders_count'], 'description' => __('dashboard.control.orders_help')]];
        foreach ($report['currency_totals'] as $currency) {
            $cards[] = ['key' => 'orders-'.$currency['currency'], 'label' => __('dashboard.control.order_amount').' · '.$currency['currency'], 'value' => $currency['total'], 'description' => __('dashboard.control.order_amount_help')];
            $cards[] = ['key' => 'average-'.$currency['currency'], 'label' => __('dashboard.control.average_order').' · '.$currency['currency'], 'value' => $currency['average_check'] ?? '—', 'description' => __('dashboard.control.average_help')];
        }
        foreach ($report['payment_currency_totals'] as $currency) {
            $cards[] = ['key' => 'payments-'.$currency['currency'], 'label' => __('dashboard.control.payments').' · '.$currency['currency'], 'value' => $currency['total'], 'description' => __('dashboard.control.payments_help')];
        }

        return $cards;
    }

    /** @param Collection<int,Branch> $branches @param array<string,Collection<int,int>> $access @return list<array<string,mixed>> */
    private function operationCards(User $user, Collection $branches, array $access, ?Branch $selected): array
    {
        $counts = $this->waiterTables->operationCounts($user, $selected);
        $cards = [];
        $definitions = [
            'all' => ['active_session_count', 'dashboard.control.operation_all', 'dashboard.control.operation_all_help'],
            'pending' => ['new_draft_count', 'dashboard.control.operation_pending', 'dashboard.control.operation_pending_help'],
            'ready' => ['ready_item_count', 'dashboard.control.operation_ready', 'dashboard.control.operation_ready_help'],
            'calls' => ['waiter_call_count', 'dashboard.control.operation_calls', 'dashboard.control.operation_calls_help'],
            'bills' => ['bill_request_count', 'dashboard.control.operation_bills', 'dashboard.control.operation_bills_help'],
        ];
        foreach ($definitions as $attention => [$key, $label, $description]) {
            $canOpen = $access['waiter']->isNotEmpty();
            $cards[] = ['key' => $attention, 'label' => __($label), 'description' => __($description),
                'value' => $canOpen ? $counts[$key] : null, 'href' => $canOpen && $selected instanceof Branch ? route('restaurant.waiter.dashboard', ['branch' => $selected->id, 'attention' => $attention, 'zone' => 'all']) : null,
                'tone' => $attention !== 'all' && $counts[$key] > 0 ? 'warning' : 'neutral', 'is_available' => $canOpen, 'requires_branch' => $selected === null];
        }

        return $cards;
    }

    /**
     * @param  Collection<int, covariant int>  $departmentIds
     * @return Collection<int, int>
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
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     * @return Collection<int, Branch>
     */
    private function branches(Collection $branchIds, User $user): Collection
    {
        if ($branchIds->isEmpty()) {
            return collect();
        }

        return Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'currency', 'timezone', 'is_active', 'is_temporarily_closed', 'temporary_closed_reason', 'temporary_closed_until'])
            ->with(['organization:id,name', 'brand:id,name'])
            ->whereIn('id', $branchIds)
            ->whereIn('id', $this->resolveAccessibleBranchIds->authorizedBranchQuery($user))
            ->orderBy('name')
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
     * @param  array<string, Collection<int, covariant int>>  $access
     * @return list<array{label: string, description: string, icon: string, href: string|null, is_available: bool, requires_branch: bool}>
     */
    private function quickActions(array $access, ?Branch $selected): array
    {
        return [
            $this->branchQuickAction(
                label: 'Menu',
                description: 'Manage branch menu',
                icon: 'book-open',
                routeName: 'organizations.brands.branches.menu.index',
                branchIds: $access['menu'], selected: $selected,
            ),
            $this->branchQuickAction(
                label: 'Tables',
                description: 'Open tables and manage service points',
                icon: 'squares-2x2',
                routeName: 'organizations.brands.branches.service-points.index',
                branchIds: $access['service_points'], selected: $selected,
            ),
            $this->branchQuickAction(
                label: 'QR',
                description: 'Print permanent branch QR codes',
                icon: 'qr-code',
                routeName: 'organizations.brands.branches.qr.print',
                branchIds: $access['qr'], selected: $selected,
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
                isAvailable: $access['waiter']->isNotEmpty(), branchId: $selected?->id,
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
                isAvailable: $access['reports']->isNotEmpty(), branchId: $selected?->id,
            ),
        ];
    }

    /**
     * @param  Collection<int, covariant int>  $branchIds
     * @return array{label: string, description: string, icon: string, href: string|null, is_available: bool, requires_branch: bool}
     */
    private function branchQuickAction(string $label, string $description, string $icon, string $routeName, Collection $branchIds, ?Branch $selected): array
    {
        $branch = $selected instanceof Branch && $branchIds->contains($selected->id) ? $selected : null;

        return [
            'label' => $label,
            'description' => $description,
            'icon' => $icon,
            'href' => $branch instanceof Branch ? route($routeName, [
                'organization' => $branch->organization_id,
                'brand' => $branch->brand_id,
                'branch' => $branch->id,
            ]) : null,
            'is_available' => $branchIds->isNotEmpty(), 'requires_branch' => $branchIds->isNotEmpty() && $selected === null,
        ];
    }

    /**
     * @return array{label: string, description: string, icon: string, href: string|null, is_available: bool, requires_branch: bool}
     */
    private function screenQuickAction(string $label, string $description, string $icon, string $routeName, bool $isAvailable, ?int $branchId = null): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'icon' => $icon,
            'href' => $isAvailable && ($routeName !== 'restaurant.waiter.dashboard' || $branchId !== null) ? route($routeName, $branchId === null ? [] : ['branch' => $branchId]) : null,
            'is_available' => $isAvailable, 'requires_branch' => $isAvailable && $routeName === 'restaurant.waiter.dashboard' && $branchId === null,
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
