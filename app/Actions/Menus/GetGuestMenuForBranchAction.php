<?php

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Enums\SupportedLocale;
use App\Models\BranchSetting;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class GetGuestMenuForBranchAction
{
    private const CACHE_SECONDS = 300;

    private const CACHE_STORE = 'database';

    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 3;

    /**
     * @return array{language: string, default_language: string, menu: array{id: int, name: string}|null, categories: list<array<string, mixed>>}
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
        return 'guest-menu:branch:'.$branchId.':language:'.self::normalizeLanguageCode($languageCode);
    }

    public static function lockKey(int $branchId, string $languageCode = 'en'): string
    {
        return self::cacheKey($branchId, $languageCode).':lock';
    }

    public static function forgetForBranch(int $branchId): void
    {
        $cache = self::cache();

        foreach (self::supportedLanguageCodes() as $languageCode) {
            $cache->forget(self::cacheKey($branchId, $languageCode));
        }

        $cache->forget(self::legacyCacheKey($branchId));
    }

    public static function cacheStore(): string
    {
        return self::CACHE_STORE;
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

    /**
     * @return array{language: string, default_language: string, menu: array{id: int, name: string}|null, categories: list<array<string, mixed>>}
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
     * @return array{language: string, default_language: string, menu: array{id: int, name: string}|null, categories: list<array<string, mixed>>}
     */
    private function buildMenuPayload(int $branchId, string $languageCode, string $defaultLanguage): array
    {
        $menu = Menu::query()
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
                            'price',
                            'image',
                            'weight',
                            'volume',
                            'calories',
                            'is_available',
                            'sort_order',
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
                                        'price_delta',
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
            ->where('branch_id', $branchId)
            ->where('status', MenuStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->first();

        if (! $menu instanceof Menu) {
            return [
                'language' => $languageCode,
                'default_language' => $defaultLanguage,
                'menu' => null,
                'categories' => [],
            ];
        }

        return [
            'language' => $languageCode,
            'default_language' => $defaultLanguage,
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
            ],
            'categories' => $menu->categories
                ->map(fn (MenuCategory $category): array => $this->categoryPayload($category))
                ->values()
                ->all(),
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
     * @return array{id: int, name: string, description: string|null, icon: string|null, items: list<array<string, mixed>>}
     */
    private function categoryPayload(MenuCategory $category): array
    {
        /** @var MenuCategoryTranslation|null $translation */
        $translation = $category->translations->first();

        return [
            'id' => $category->id,
            'name' => $this->translatedText($translation?->name, $category->name),
            'description' => $this->translatedText($translation?->description, $category->description),
            'icon' => $category->icon,
            'items' => $category->items
                ->map(fn (MenuItem $item): array => $this->itemPayload($item))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, description: string|null, price: string, image_url: string|null, weight: string|null, volume: string|null, calories: int|null, is_available: bool, modifier_groups: list<array<string, mixed>>}
     */
    private function itemPayload(MenuItem $item): array
    {
        /** @var MenuItemTranslation|null $translation */
        $translation = $item->translations->first();

        return [
            'id' => $item->id,
            'name' => $this->translatedText($translation?->name, $item->name),
            'description' => $this->translatedText($translation?->description, $item->description),
            'price' => $item->price,
            'image_url' => $item->imageUrl(),
            'weight' => $item->weight,
            'volume' => $item->volume,
            'calories' => $item->calories,
            'is_available' => $item->is_available,
            'modifier_groups' => $item->modifierGroups
                ->map(fn (ModifierGroup $modifierGroup): array => $this->modifierGroupPayload($modifierGroup))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta: string}>}
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
                    'price_delta' => $modifierOption->price_delta,
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
