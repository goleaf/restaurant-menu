<?php

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
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
     * @return array{menu: array{id: int, name: string}|null, categories: list<array{id: int, name: string, description: string|null, icon: string|null, items: list<array{id: int, name: string, description: string|null, price: string, image_url: string|null, weight: string|null, volume: string|null, calories: int|null, is_available: bool}>}>}
     */
    public function handle(int $branchId): array
    {
        $cache = self::cache();
        $cacheKey = self::cacheKey($branchId);
        $cachedPayload = $cache->get($cacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        try {
            return $cache->withoutOverlapping(
                self::lockKey($branchId),
                fn (): array => $this->rememberFreshPayload($cache, $branchId),
                self::LOCK_SECONDS,
                self::LOCK_WAIT_SECONDS,
            );
        } catch (LockTimeoutException) {
            return $this->buildMenuPayload($branchId);
        }
    }

    public static function cacheKey(int $branchId): string
    {
        return 'guest-menu:branch:'.$branchId;
    }

    public static function lockKey(int $branchId): string
    {
        return 'guest-menu:branch:'.$branchId.':lock';
    }

    public static function forgetForBranch(int $branchId): void
    {
        self::cache()->forget(self::cacheKey($branchId));
    }

    public static function cacheStore(): string
    {
        return self::CACHE_STORE;
    }

    private static function cache(): CacheRepository
    {
        return Cache::store(self::CACHE_STORE);
    }

    /**
     * @return array{menu: array{id: int, name: string}|null, categories: list<array{id: int, name: string, description: string|null, icon: string|null, items: list<array{id: int, name: string, description: string|null, price: string, image_url: string|null, weight: string|null, volume: string|null, calories: int|null, is_available: bool}>}>}
     */
    private function rememberFreshPayload(CacheRepository $cache, int $branchId): array
    {
        $cacheKey = self::cacheKey($branchId);
        $cachedPayload = $cache->get($cacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $payload = $this->buildMenuPayload($branchId);

        $cache->put($cacheKey, $payload, self::CACHE_SECONDS);

        return $payload;
    }

    /**
     * @return array{menu: array{id: int, name: string}|null, categories: list<array{id: int, name: string, description: string|null, icon: string|null, items: list<array{id: int, name: string, description: string|null, price: string, image_url: string|null, weight: string|null, volume: string|null, calories: int|null, is_available: bool}>}>}
     */
    private function buildMenuPayload(int $branchId): array
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
                        ])->orderBy('sort_order')->orderBy('name')->orderBy('id'),
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
                'menu' => null,
                'categories' => [],
            ];
        }

        return [
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
            ],
            'categories' => $menu->categories
                ->map(fn (MenuCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'icon' => $category->icon,
                    'items' => $category->items
                        ->map(fn (MenuItem $item): array => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'description' => $item->description,
                            'price' => $item->price,
                            'image_url' => $item->imageUrl(),
                            'weight' => $item->weight,
                            'volume' => $item->volume,
                            'calories' => $item->calories,
                            'is_available' => $item->is_available,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
