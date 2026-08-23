<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\MenuStatus;
use App\Enums\SupportedLocale;
use App\Models\BranchSetting;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
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

    public function __construct(
        private readonly GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus,
    ) {}

    /**
     * @return array{language: string, default_language: string, availability: array<string, mixed>, menu: array{id: int, name: string}|null, menus: list<array<string, mixed>>, unavailable_menus: list<array<string, mixed>>, categories: list<array<string, mixed>>}
     */
    public function handle(int $branchId, ?string $languageCode = null): array
    {
        $defaultLanguage = $this->defaultLanguageForBranch($branchId);
        $languageCode = self::normalizeLanguageCode($languageCode, $defaultLanguage);
        $cache = self::cache();
        $cacheKey = self::cacheKey($branchId, $languageCode);
        $cachedPayload = $cache->get($cacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        try {
            return $cache->withoutOverlapping(
                self::lockKey($branchId, $languageCode),
                fn (): array => $this->rememberFreshPayload($cache, $branchId, $languageCode, $defaultLanguage),
                self::LOCK_SECONDS,
                self::LOCK_WAIT_SECONDS,
            );
        } catch (LockTimeoutException) {
            return $this->buildMenuPayload($branchId, $languageCode, $defaultLanguage);
        }
    }

    public static function cacheKey(int $branchId, string $languageCode = 'en'): string
    {
        return 'guest-menu:v4:branch:'.$branchId.':language:'.self::normalizeLanguageCode($languageCode);
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
        return 'guest-menu:v3:branch:'.$branchId.':language:'.self::normalizeLanguageCode($languageCode);
    }

    /**
     * @return array{language: string, default_language: string, availability: array<string, mixed>, menu: array{id: int, name: string}|null, menus: list<array<string, mixed>>, unavailable_menus: list<array<string, mixed>>, categories: list<array<string, mixed>>}
     */
    private function rememberFreshPayload(CacheRepository $cache, int $branchId, string $languageCode, string $defaultLanguage): array
    {
        $cacheKey = self::cacheKey($branchId, $languageCode);
        $cachedPayload = $cache->get($cacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $payload = $this->buildMenuPayload($branchId, $languageCode, $defaultLanguage);

        $cache->put($cacheKey, $payload, self::CACHE_SECONDS);

        return $payload;
    }

    /**
     * @return array{language: string, default_language: string, availability: array<string, mixed>, menu: array{id: int, name: string}|null, menus: list<array<string, mixed>>, unavailable_menus: list<array<string, mixed>>, categories: list<array<string, mixed>>}
     */
    private function buildMenuPayload(int $branchId, string $languageCode, string $defaultLanguage): array
    {
        $availabilityResult = $this->availableMenusForBranch($branchId);
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
                'status',
                'sort_order',
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
                    ])
                    ->whereIn('menu_id', $availableMenus->pluck('id')->all())
                    ->where('is_active', true)
                    ->with([
                        'translations' => fn ($translationQuery) => $translationQuery->select([
                            'id',
                            'menu_category_id',
                            'language_code',
                            'name',
                            'description',
                        ])->where('language_code', $languageCode),
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
                            'weight',
                            'volume',
                            'calories',
                            'is_available',
                            'sort_order',
                        ])
                            ->withExists([
                                'variants as has_variants',
                                'variants as has_available_variants' => fn ($variantQuery) => $variantQuery
                                    ->where('is_available', true),
                            ])
                            ->with([
                                'translations' => fn ($translationQuery) => $translationQuery->select([
                                    'id',
                                    'menu_item_id',
                                    'language_code',
                                    'name',
                                    'description',
                                ])->where('language_code', $languageCode),
                                'modifierGroups' => fn ($modifierGroupQuery) => $modifierGroupQuery->select([
                                    'modifier_groups.id',
                                    'modifier_groups.branch_id',
                                    'modifier_groups.name',
                                    'modifier_groups.is_required',
                                    'modifier_groups.min_select',
                                    'modifier_groups.max_select',
                                    'modifier_groups.sort_order',
                                ])->with([
                                    'options' => fn ($optionQuery) => $optionQuery->select([
                                        'id',
                                        'modifier_group_id',
                                        'name',
                                        'price_delta_cents',
                                        'is_available',
                                        'sort_order',
                                    ])
                                        ->where('is_available', true)
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

        $this->loadAvailableVariants($menus, $languageCode);

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
    private function availableMenusForBranch(int $branchId): array
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
                'status',
                'sort_order',
            ])
            ->with([
                'branch' => fn ($query) => $query->select(['id', 'timezone']),
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
            $availability = $this->getMenuAvailabilityStatus->handle($menu);

            if ($availability['is_available']) {
                $availableMenus->push($menu);
                $availableStatuses[$menu->id] = $availability;
                $firstAvailableStatus ??= $availability;

                continue;
            }

            $unavailableMenus[] = [
                'id' => $menu->id,
                'name' => $menu->name,
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
                availableMenuCount: $availableMenus->count(),
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
     * @return array{id: int, name: string, availability: array<string, mixed>, categories: list<array<string, mixed>>}
     */
    private function menuPayload(Menu $menu, array $availability, string $languageCode): array
    {
        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'availability' => $availability,
            'categories' => $menu->categories
                ->map(fn (MenuCategory $category): array => $this->categoryPayload($category, $languageCode))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, description: string|null, icon: string|null, items: list<array<string, mixed>>}
     */
    private function categoryPayload(MenuCategory $category, string $languageCode): array
    {
        /** @var MenuCategoryTranslation|null $translation */
        $translation = $category->translations->first();

        return [
            'id' => $category->id,
            'name' => $this->translatedText($translation?->name, $category->name),
            'description' => $this->translatedText($translation?->description, $category->description),
            'icon' => $category->icon,
            'items' => $category->items
                ->map(fn (MenuItem $item): array => $this->itemPayload($item, $languageCode))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, description: string|null, price_cents: int, allergens: list<array{value: string, label: string}>, dietary_labels: list<array{value: string, label: string}>, image_url: string|null, weight: string|null, volume: string|null, calories: int|null, is_available: bool, variants: list<array<string, mixed>>, modifier_groups: list<array<string, mixed>>}
     */
    private function itemPayload(MenuItem $item, string $languageCode): array
    {
        /** @var MenuItemTranslation|null $translation */
        $translation = $item->translations->first();

        return [
            'id' => $item->id,
            'name' => $this->translatedText($translation?->name, $item->name),
            'description' => $this->translatedText($translation?->description, $item->description),
            'price_cents' => $item->price_cents,
            'allergens' => $this->selectedLabelOptions($item->allergens, MenuAllergen::options($languageCode)),
            'dietary_labels' => $this->selectedLabelOptions($item->dietary_labels, MenuDietaryLabel::options($languageCode)),
            'image_url' => $item->imageUrl(),
            'weight' => $item->weight,
            'volume' => $item->volume,
            'calories' => $item->calories,
            'is_available' => $item->is_available && (
                ! (bool) $item->getAttribute('has_variants')
                || (bool) $item->getAttribute('has_available_variants')
            ),
            'variants' => $item->variants
                ->map(fn (MenuItemVariant $variant): array => [
                    'id' => $variant->id,
                    'type' => $variant->type->value,
                    'type_label' => $variant->type->label($languageCode),
                    'name' => $this->translatedText(
                        is_string($variant->getAttribute('localized_name'))
                            ? $variant->getAttribute('localized_name')
                            : null,
                        $variant->name,
                    ),
                    'price_cents' => $variant->price_cents,
                    'weight' => $variant->weight,
                    'volume' => $variant->volume,
                    'is_default' => $variant->is_default,
                ])
                ->values()
                ->all(),
            'modifier_groups' => $item->modifierGroups
                ->map(fn (ModifierGroup $modifierGroup): array => $this->modifierGroupPayload($modifierGroup))
                ->values()
                ->all(),
        ];
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
            fn (MenuItem $item): bool => ! (bool) $item->getAttribute('has_available_variants'),
        );

        $itemsWithoutVariants->each(
            fn (MenuItem $item): MenuItem => $item->setRelation('variants', new EloquentCollection),
        );

        $itemsWithVariants = $items->filter(
            fn (MenuItem $item): bool => (bool) $item->getAttribute('has_available_variants'),
        );

        if ($itemsWithVariants->isEmpty()) {
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
                ])
                ->where('is_available', true),
        ]);
    }

    /**
     * @param  list<string>  $selectedValues
     * @param  list<array{value: string, label: string}>  $options
     * @return list<array{value: string, label: string}>
     */
    private function selectedLabelOptions(array $selectedValues, array $options): array
    {
        return array_values(array_filter(
            $options,
            fn (array $option): bool => in_array($option['value'], $selectedValues, true),
        ));
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

    /**
     * @return array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta_cents: int}>}
     */
    private function modifierGroupPayload(ModifierGroup $modifierGroup): array
    {
        return [
            'id' => $modifierGroup->id,
            'name' => $modifierGroup->name,
            'is_required' => $modifierGroup->is_required,
            'min_select' => $modifierGroup->min_select,
            'max_select' => $modifierGroup->max_select,
            'options' => $modifierGroup->options
                ->map(fn (ModifierOption $modifierOption): array => [
                    'id' => $modifierOption->id,
                    'name' => $modifierOption->name,
                    'price_delta_cents' => $modifierOption->price_delta_cents,
                ])
                ->values()
                ->all(),
        ];
    }

    private function translatedText(?string $translatedText, ?string $fallbackText): ?string
    {
        if (filled($translatedText)) {
            return $translatedText;
        }

        return $fallbackText;
    }
}
