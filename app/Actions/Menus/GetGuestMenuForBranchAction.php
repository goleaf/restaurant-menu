<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Enums\SupportedLocale;
use App\Models\BranchSetting;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\MenuItemVariantTranslation;
use App\Models\MenuTranslation;
use App\Models\ModifierGroupTranslation;
use App\Models\ModifierOptionTranslation;
use App\Services\Availability\AvailabilityEvaluator;
use App\Services\Menus\GuestMenuItemPresenter;
use App\Support\Availability\AvailabilityResult;
use App\Support\BranchReportCacheVersion;
use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

class GetGuestMenuForBranchAction
{
    private const CACHE_SECONDS = 60;

    private const CACHE_STORE = 'database';

    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 3;

    private CarbonImmutable $evaluatedAt;

    private CarbonImmutable $cacheUntil;

    public function __construct(
        private readonly GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus,
        private readonly AvailabilityEvaluator $availability,
        private readonly GuestMenuItemPresenter $itemPresenter = new GuestMenuItemPresenter,
    ) {}

    /**
     * @return array{language: string, default_language: string, availability: array<string, mixed>, menu: array{id: int, name: string}|null, menus: list<array<string, mixed>>, unavailable_menus: list<array<string, mixed>>, categories: list<array<string, mixed>>}
     */
    public function handle(int $branchId, ?string $languageCode = null): array
    {
        $defaultLanguage = $this->defaultLanguageForBranch($branchId);
        $languageCode = self::normalizeLanguageCode($languageCode, $defaultLanguage);
        $cache = self::cache();
        $at = CarbonImmutable::now();
        if (! $cache instanceof Repository) {
            return $this->buildMenuPayload($branchId, $languageCode, $defaultLanguage, $at);
        }
        $generation = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branchId]));
        $cacheKey = self::cacheKey($branchId, $languageCode);
        $cachedPayload = $this->cachedPayload($cache, $cacheKey, $generation, $at);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        try {
            return $cache->withoutOverlapping(
                self::lockKey($branchId, $languageCode),
                fn (): array => $this->rememberFreshPayload($cache, $branchId, $languageCode, $defaultLanguage, $generation, $at),
                self::LOCK_SECONDS,
                self::LOCK_WAIT_SECONDS,
            );
        } catch (LockTimeoutException) {
            return $this->buildMenuPayload($branchId, $languageCode, $defaultLanguage, $at);
        }
    }

    public static function cacheKey(int $branchId, string $languageCode = 'en'): string
    {
        return 'guest-menu:v8:branch:'.$branchId.':language:'.self::normalizeLanguageCode($languageCode);
    }

    public static function lockKey(int $branchId, string $languageCode = 'en'): string
    {
        return self::cacheKey($branchId, $languageCode).':lock';
    }

    public static function cacheStore(): string
    {
        return self::CACHE_STORE;
    }

    /**
     * @return list<string>
     */
    public static function cacheKeysForBranch(int $branchId): array
    {
        if ($branchId < 1) {
            return [];
        }

        return [
            ...array_map(
                fn (string $languageCode): string => self::cacheKey($branchId, $languageCode),
                self::supportedLanguageCodes(),
            ),
            ...array_map(
                fn (string $languageCode): string => 'guest-menu:v7:branch:'.$branchId.':language:'.$languageCode,
                self::supportedLanguageCodes(),
            ),
            ...array_map(
                fn (string $languageCode): string => 'guest-menu:v6:branch:'.$branchId.':language:'.$languageCode,
                self::supportedLanguageCodes(),
            ),
            ...array_map(
                fn (string $languageCode): string => 'guest-menu:v5:branch:'.$branchId.':language:'.$languageCode,
                self::supportedLanguageCodes(),
            ),
            ...array_map(
                fn (string $languageCode): string => self::previousCacheKey($branchId, $languageCode),
                self::supportedLanguageCodes(),
            ),
            self::legacyCacheKey($branchId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function supportedLanguageLabels(): array
    {
        return SupportedLocale::labels();
    }

    /**
     * @return list<string>
     */
    public static function supportedLanguageCodes(): array
    {
        return SupportedLocale::values();
    }

    public static function normalizeLanguageCode(?string $languageCode, string $fallback = 'en'): string
    {
        return SupportedLocale::normalize($languageCode, $fallback);
    }

    public function resolveLanguageForBranch(int $branchId, ?string $languageCode = null): string
    {
        return self::normalizeLanguageCode($languageCode, $this->defaultLanguageForBranch($branchId));
    }

    private static function cache(): CacheRepository
    {
        return Cache::store(self::CACHE_STORE);
    }

    private static function legacyCacheKey(int $branchId): string
    {
        return 'guest-menu:branch:'.$branchId;
    }

    private static function previousCacheKey(int $branchId, string $languageCode): string
    {
        return 'guest-menu:v4:branch:'.$branchId.':language:'.self::normalizeLanguageCode($languageCode);
    }

    /**
     * @return array{language: string, default_language: string, availability: array<string, mixed>, menu: array{id: int, name: string}|null, menus: list<array<string, mixed>>, unavailable_menus: list<array<string, mixed>>, categories: list<array<string, mixed>>}
     */
    private function rememberFreshPayload(CacheRepository $cache, int $branchId, string $languageCode, string $defaultLanguage, string $generation, CarbonImmutable $at): array
    {
        $cacheKey = self::cacheKey($branchId, $languageCode);
        $cachedPayload = $this->cachedPayload($cache, $cacheKey, $generation, $at);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $payload = $this->buildMenuPayload($branchId, $languageCode, $defaultLanguage, $at);

        if ($this->cacheUntil->greaterThan($at)) {
            $cache->put($cacheKey, ['generation' => $generation, 'valid_until' => $this->cacheUntil->toIso8601String(), 'payload' => $payload], $this->cacheUntil);
        }

        return $payload;
    }

    /**
     * @return array{language: string, default_language: string, availability: array<string, mixed>, menu: array{id: int, name: string}|null, menus: list<array<string, mixed>>, unavailable_menus: list<array<string, mixed>>, categories: list<array<string, mixed>>}
     */
    private function buildMenuPayload(int $branchId, string $languageCode, string $defaultLanguage, CarbonImmutable $at): array
    {
        $this->evaluatedAt = $at;
        $this->cacheUntil = $at->addSeconds(self::CACHE_SECONDS);
        $hiddenUntil = MenuItem::query()->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId)->where('status', MenuStatus::Active->value))
            ->where('hidden_until', '>', $at)->min('hidden_until');
        if (is_string($hiddenUntil)) {
            $this->includeBoundary(CarbonImmutable::parse($hiddenUntil));
        }
        $availabilityResult = $this->availableMenusForBranch($branchId, $languageCode);
        /** @var EloquentCollection<int, Menu> $availableMenus */
        $availableMenus = $availabilityResult['available_menus'];
        /** @var array<int, array<string, mixed>> $availableMenuStatuses */
        $availableMenuStatuses = $availabilityResult['available_statuses'];
        /** @var list<array<string, mixed>> $unavailableMenus */
        $unavailableMenus = $availabilityResult['unavailable_menus'];
        /** @var array<string, mixed> $availability */
        $availability = $availabilityResult['availability'];

        if ($availableMenus->isEmpty()) {
            return [
                'language' => $languageCode,
                'default_language' => $defaultLanguage,
                'availability' => $availability,
                'menu' => null,
                'menus' => [],
                'unavailable_menus' => $unavailableMenus,
                'categories' => [],
                'has_allergen_information' => false,
            ];
        }

        $menus = Menu::query()
            ->select([
                'id',
                'branch_id',
                'name',
                'status', 'schedule_is_closed', 'schedule_version', 'deleted_at',
                'sort_order',
            ])
            ->addSelect([
                'localized_name' => MenuTranslation::query()
                    ->select('name')
                    ->whereColumn('menu_id', 'menus.id')
                    ->where('language_code', $languageCode)
                    ->limit(1),
            ])
            ->with([
                'categories' => fn ($query) => $query
                    ->select([
                        'id',
                        'menu_id',
                        'parent_id',
                        'name',
                        'description',
                        'icon',
                        'sort_order',
                        'is_active',
                        'deleted_at',
                    ])
                    ->addSelect([
                        'localized_name' => MenuCategoryTranslation::query()
                            ->select('name')
                            ->whereColumn('menu_category_id', $query->qualifyColumn('id'))
                            ->where('language_code', $languageCode)
                            ->limit(1),
                        'localized_description' => MenuCategoryTranslation::query()
                            ->select('description')
                            ->whereColumn('menu_category_id', $query->qualifyColumn('id'))
                            ->where('language_code', $languageCode)
                            ->limit(1),
                    ])
                    ->withExists([
                        'translations as has_localized_content' => fn ($translationQuery) => $translationQuery
                            ->where('language_code', $languageCode),
                    ])
                    ->whereIn('menu_id', $availableMenus->pluck('id')->all())
                    ->where('is_active', true)
                    ->with([
                        'items' => fn ($itemQuery) => $itemQuery->select([
                            'id',
                            'menu_id',
                            'category_id',
                            'name',
                            'description',
                            'price_cents',
                            'allergens',
                            'dietary_labels',
                            'image',
                            'image_presentation',
                            'weight',
                            'volume',
                            'calories',
                            'is_available',
                            'hidden_until',
                            'deleted_at',
                            'sort_order',
                        ])
                            ->addSelect([
                                'localized_name' => MenuItemTranslation::query()
                                    ->select('name')
                                    ->whereColumn('menu_item_id', $itemQuery->qualifyColumn('id'))
                                    ->where('language_code', $languageCode)
                                    ->limit(1),
                                'localized_description' => MenuItemTranslation::query()
                                    ->select('description')
                                    ->whereColumn('menu_item_id', $itemQuery->qualifyColumn('id'))
                                    ->where('language_code', $languageCode)
                                    ->limit(1),
                            ])
                            ->where(fn ($visibilityQuery) => $visibilityQuery
                                ->whereNull('hidden_until')
                                ->orWhere('hidden_until', '<=', $this->evaluatedAt))
                            ->withExists([
                                'translations as has_localized_content' => fn ($translationQuery) => $translationQuery
                                    ->where('language_code', $languageCode),
                                'variants as has_variants',
                                'variants as has_available_variants' => fn ($variantQuery) => $variantQuery
                                    ->where('is_available', true),
                            ])
                            ->with([
                                'modifierGroups' => fn ($modifierGroupQuery) => $modifierGroupQuery->select([
                                    'modifier_groups.id',
                                    'modifier_groups.branch_id',
                                    'modifier_groups.name',
                                    'modifier_groups.is_required',
                                    'modifier_groups.min_select',
                                    'modifier_groups.max_select',
                                    'modifier_groups.sort_order',
                                ])->addSelect([
                                    'localized_name' => ModifierGroupTranslation::query()
                                        ->select('name')
                                        ->whereColumn('modifier_group_id', 'modifier_groups.id')
                                        ->where('language_code', $languageCode)
                                        ->limit(1),
                                ])->with([
                                    'options' => fn ($optionQuery) => $optionQuery->select([
                                        'id',
                                        'modifier_group_id',
                                        'name',
                                        'price_delta_cents',
                                        'is_available',
                                        'sort_order',
                                    ])
                                        ->addSelect([
                                            'localized_name' => ModifierOptionTranslation::query()
                                                ->select('name')
                                                ->whereColumn('modifier_option_id', 'modifier_options.id')
                                                ->where('language_code', $languageCode)
                                                ->limit(1),
                                        ])
                                        ->orderBy('sort_order')
                                        ->orderBy('name')
                                        ->orderBy('id'),
                                ]),
                            ])
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->orderBy('id'),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->orderBy('id'),
            ])
            ->whereKey($availableMenus->pluck('id')->all())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        foreach ($menus as $menu) {
            $context = $availableMenus->firstWhere('id', $menu->id);
            $menu->setRelation('branch', $context->branch);
            $menu->setRelation('availabilitySchedules', $context->availabilitySchedules);
            foreach ($menu->categories as $category) {
                foreach ($category->items as $item) {
                    $item->setRelation('menu', $menu);
                    $item->setRelation('category', $category);
                }
            }
        }
        $this->loadAvailableVariants($menus, $languageCode);
        $itemDecisions = [];
        $items = $menus->flatMap(fn (Menu $menu) => $menu->categories->flatMap(fn (MenuCategory $category) => $category->items));
        foreach ($items->chunk(100) as $batch) {
            $itemDecisions += $this->availability->items($batch, $at);
        }

        if ($menus->isEmpty()) {
            return [
                'language' => $languageCode,
                'default_language' => $defaultLanguage,
                'availability' => $this->emptyAvailabilityStatus(),
                'menu' => null,
                'menus' => [],
                'unavailable_menus' => $unavailableMenus,
                'categories' => [],
                'has_allergen_information' => false,
            ];
        }

        $menuPayloads = $menus
            ->map(fn (Menu $menu): array => $this->menuPayload(
                $menu,
                $availableMenuStatuses[$menu->id] ?? $this->emptyAvailabilityStatus(),
                $languageCode,
                $itemDecisions,
            ))
            ->values()
            ->all();
        $firstMenuPayload = $menuPayloads[0] ?? null;

        return [
            'language' => $languageCode,
            'default_language' => $defaultLanguage,
            'availability' => $availability,
            'menu' => $firstMenuPayload === null ? null : [
                'id' => $firstMenuPayload['id'],
                'name' => $firstMenuPayload['name'],
            ],
            'menus' => $menuPayloads,
            'unavailable_menus' => $unavailableMenus,
            'categories' => $firstMenuPayload['categories'] ?? [],
            'has_allergen_information' => $this->hasAllergenInformation($menuPayloads),
        ];
    }

    /**
     * @return array{available_menus: EloquentCollection<int, Menu>, available_statuses: array<int, array<string, mixed>>, unavailable_menus: list<array<string, mixed>>, availability: array<string, mixed>}
     */
    private function availableMenusForBranch(int $branchId, string $languageCode): array
    {
        $availableMenus = new EloquentCollection;
        $availableStatuses = [];
        $unavailableMenus = [];
        $firstAvailableStatus = null;
        $nextUnavailableStatus = null;
        $menus = Menu::query()
            ->select([
                'id',
                'branch_id',
                'name',
                'status', 'schedule_is_closed', 'schedule_version', 'deleted_at',
                'sort_order',
            ])
            ->addSelect([
                'localized_name' => MenuTranslation::query()
                    ->select('name')
                    ->whereColumn('menu_id', 'menus.id')
                    ->where('language_code', $languageCode)
                    ->limit(1),
            ])
            ->with([
                'branch' => fn ($query) => $query->select(AvailabilityEvaluator::branchColumns()),
                'branch.openingHours', 'branch.scheduleExceptions',
                'branch.organization:id,deleted_at', 'branch.organization.subscription:id,organization_id,status',
                'branch.brand:id,organization_id,deleted_at',
                'availabilitySchedules' => fn ($query) => $query->select([
                    'id',
                    'menu_id',
                    'day_of_week',
                    'starts_at',
                    'ends_at',
                ]),
            ])
            ->where('branch_id', $branchId)
            ->where('status', MenuStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        foreach ($menus as $menu) {
            $availability = $this->getMenuAvailabilityStatus->handle($menu, $this->evaluatedAt);
            foreach (['next_change_at', 'next_available_at'] as $key) {
                if (is_string($availability[$key] ?? null)) {
                    $this->includeBoundary(CarbonImmutable::parse($availability[$key]));
                }
            }

            $decision = $this->availability->menu($menu, $this->evaluatedAt);
            $ownScheduleClosed = in_array('menu_schedule_closed', $availability['reason_codes'], true);
            if ($decision->visible && ! $ownScheduleClosed) {
                $availableMenus->push($menu);
                $availableStatuses[$menu->id] = $availability;
                if ($availability['is_available']) {
                    $firstAvailableStatus ??= $availability;
                } elseif ($this->statusIsSooner($availability, $nextUnavailableStatus)) {
                    $nextUnavailableStatus = $availability;
                }

                continue;
            }

            $unavailableMenus[] = [
                'id' => $menu->id,
                'name' => $this->translatedText(
                    is_string($menu->getAttribute('localized_name'))
                        ? $menu->getAttribute('localized_name')
                        : null,
                    $menu->name,
                ),
                'availability' => $availability,
            ];

            if ($this->statusIsSooner($availability, $nextUnavailableStatus)) {
                $nextUnavailableStatus = $availability;
            }
        }

        return [
            'available_menus' => $availableMenus,
            'available_statuses' => $availableStatuses,
            'unavailable_menus' => $unavailableMenus,
            'availability' => $this->aggregateAvailabilityStatus(
                availableMenuCount: count(array_filter($availableStatuses, fn (array $status): bool => $status['is_available'])),
                firstAvailableStatus: $firstAvailableStatus,
                nextUnavailableStatus: $nextUnavailableStatus,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $firstAvailableStatus
     * @param  array<string, mixed>|null  $nextUnavailableStatus
     * @return array<string, mixed>
     */
    private function aggregateAvailabilityStatus(int $availableMenuCount, ?array $firstAvailableStatus, ?array $nextUnavailableStatus): array
    {
        if ($availableMenuCount < 1) {
            return $nextUnavailableStatus ?? $this->emptyAvailabilityStatus();
        }

        if ($availableMenuCount === 1 && $firstAvailableStatus !== null) {
            return $firstAvailableStatus;
        }

        return [
            'is_configured' => (bool) ($firstAvailableStatus['is_configured'] ?? false),
            'is_available' => true,
            'label' => __('menu.guest.available_now'),
            'detail' => __('menu.guest.available_count', ['count' => $availableMenuCount]),
            'tone' => 'success',
            'next_available_at' => null,
            'available_until' => $firstAvailableStatus['available_until'] ?? null,
            'timezone' => (string) ($firstAvailableStatus['timezone'] ?? config('app.timezone', 'UTC')),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $currentStatus
     * @param  array<string, mixed>|null  $storedStatus
     */
    private function statusIsSooner(?array $currentStatus, ?array $storedStatus): bool
    {
        if ($currentStatus === null) {
            return false;
        }

        if ($storedStatus === null) {
            return true;
        }

        $currentNextAvailableAt = $currentStatus['next_available_at'] ?? null;
        $storedNextAvailableAt = $storedStatus['next_available_at'] ?? null;

        if (! is_string($currentNextAvailableAt)) {
            return false;
        }

        if (! is_string($storedNextAvailableAt)) {
            return true;
        }

        return strcmp($currentNextAvailableAt, $storedNextAvailableAt) < 0;
    }

    /**
     * @return array{is_configured: bool, is_available: bool, label: string, detail: string, tone: string, next_available_at: string|null, available_until: string|null, timezone: string}
     */
    private function emptyAvailabilityStatus(): array
    {
        return [
            'is_configured' => false,
            'is_available' => false,
            'label' => __('menu.guest.unavailable'),
            'detail' => __('menu.guest.unavailable_description'),
            'tone' => 'muted',
            'next_available_at' => null,
            'available_until' => null,
            'timezone' => config('app.timezone', 'UTC'),
        ];
    }

    private function defaultLanguageForBranch(int $branchId): string
    {
        $languageCode = BranchSetting::query()
            ->select('default_language')
            ->where('branch_id', $branchId)
            ->value('default_language');

        return self::normalizeLanguageCode(is_string($languageCode) ? $languageCode : null);
    }

    /**
     * @param  array<int, AvailabilityResult>  $itemDecisions
     * @return array{id: int, name: string, availability: array<string, mixed>, categories: list<array<string, mixed>>}
     */
    private function menuPayload(Menu $menu, array $availability, string $languageCode, array $itemDecisions): array
    {
        return [
            'id' => $menu->id,
            'name' => $this->translatedText(
                is_string($menu->getAttribute('localized_name'))
                    ? $menu->getAttribute('localized_name')
                    : null,
                $menu->name,
            ),
            'availability' => $availability,
            'categories' => $menu->categories
                ->map(fn (MenuCategory $category): array => $this->categoryPayload($category, $languageCode, $itemDecisions))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, AvailabilityResult>  $itemDecisions
     * @return array{id: int, name: string, description: string|null, icon: string|null, items: list<array<string, mixed>>}
     */
    private function categoryPayload(MenuCategory $category, string $languageCode, array $itemDecisions): array
    {
        return [
            'id' => $category->id,
            'name' => $this->translatedText(
                is_string($category->getAttribute('localized_name'))
                    ? $category->getAttribute('localized_name')
                    : null,
                $category->name,
            ),
            'description' => $category->getAttribute('has_localized_content')
                ? $category->getAttribute('localized_description')
                : $category->description,
            'icon' => $category->icon,
            'items' => $category->items
                ->map(fn (MenuItem $item): array => $this->itemPayload($item, $languageCode, $itemDecisions[$item->id]))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, description: string|null, price_cents: int, allergens: list<array{value: string, label: string}>, dietary_labels: list<array{value: string, label: string}>, image_url: string|null, weight: string|null, volume: string|null, calories: int|null, is_available: bool, variants: list<array<string, mixed>>, modifier_groups: list<array<string, mixed>>}
     */
    private function itemPayload(MenuItem $item, string $languageCode, AvailabilityResult $decision): array
    {
        $this->includeBoundary($decision->nextChangeAt);

        return $this->itemPresenter->present($item, $languageCode, $decision);
    }

    /**
     * @param  EloquentCollection<int, Menu>  $menus
     */
    private function loadAvailableVariants(EloquentCollection $menus, string $languageCode): void
    {
        /** @var EloquentCollection<int, MenuItem> $items */
        $items = new EloquentCollection(
            $menus
                ->flatMap(fn (Menu $menu) => $menu->categories->flatMap(
                    fn (MenuCategory $category) => $category->items,
                ))
                ->all(),
        );

        $itemsWithoutVariants = $items->filter(
            fn (MenuItem $item): bool => ! (bool) $item->getAttribute('has_variants'),
        );

        $itemsWithoutVariants->each(
            fn (MenuItem $item): MenuItem => $item->setRelation('variants', new EloquentCollection),
        );

        $itemsWithVariants = $items->filter(
            fn (MenuItem $item): bool => (bool) $item->getAttribute('has_variants'),
        );

        if ($itemsWithVariants->isEmpty()) {
            $items->loadMissing(AvailabilityEvaluator::itemRelations());

            return;
        }

        $itemsWithVariants->load([
            'variants' => fn ($variantQuery) => $variantQuery
                ->select([
                    'id',
                    'menu_item_id',
                    'type',
                    'name',
                    'price_cents',
                    'weight',
                    'volume',
                    'is_default',
                    'is_available',
                    'sort_order',
                ])
                ->addSelect([
                    'localized_name' => MenuItemVariantTranslation::query()
                        ->select('name')
                        ->whereColumn('menu_item_variant_id', 'menu_item_variants.id')
                        ->where('language_code', $languageCode)
                        ->limit(1),
                ]),
        ]);
        $items->loadMissing(AvailabilityEvaluator::itemRelations());
    }

    /** @return array<string,mixed>|null */
    private function cachedPayload(CacheRepository $cache, string $key, string $generation, CarbonImmutable $at): ?array
    {
        $entry = $cache->get($key);
        if (! is_array($entry) || ($entry['generation'] ?? null) !== $generation
            || ! is_string($entry['valid_until'] ?? null) || ! is_array($entry['payload'] ?? null)) {
            return null;
        }

        return $at->lessThan(CarbonImmutable::parse($entry['valid_until'])) ? $entry['payload'] : null;
    }

    private function includeBoundary(?CarbonImmutable $boundary): void
    {
        if ($boundary !== null && $boundary->greaterThan($this->evaluatedAt) && $boundary->lessThan($this->cacheUntil)) {
            $this->cacheUntil = $boundary;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $menuPayloads
     */
    private function hasAllergenInformation(array $menuPayloads): bool
    {
        foreach ($menuPayloads as $menu) {
            foreach ($menu['categories'] ?? [] as $category) {
                foreach ($category['items'] ?? [] as $item) {
                    if (($item['allergens'] ?? []) !== []) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function translatedText(?string $translatedText, ?string $fallbackText): ?string
    {
        if (filled($translatedText)) {
            return $translatedText;
        }

        return $fallbackText;
    }
}
