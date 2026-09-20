<?php

namespace App\Actions\Branches;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Models\DatabaseCacheEntry;
use App\Support\BranchReportCacheVersion;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Connection;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;

class ForgetBranchCacheAction
{
    /**
     * @param  iterable<int, int>  $branchIds
     */
    public function handleMany(iterable $branchIds): void
    {
        $seenBranchIds = [];

        foreach ($branchIds as $branchId) {
            $branchId = (int) $branchId;

            if ($branchId < 1 || isset($seenBranchIds[$branchId])) {
                continue;
            }

            $seenBranchIds[$branchId] = true;
            $this->handle($branchId);
        }
    }

    public function handle(int $branchId, bool $guestMenu = true, bool $polling = true): void
    {
        if ($branchId < 1) {
            return;
        }

        $cache = $this->cache();
        if ($guestMenu && $cache instanceof Repository) {
            BranchReportCacheVersion::invalidate($cache, 'guest-menu', $branchId);
        }
        $cacheKeys = [
            ...($guestMenu ? GetGuestMenuForBranchAction::cacheKeysForBranch($branchId) : []),
            ...($polling ? [GetBranchPollingIntervalAction::cacheKey($branchId)] : []),
        ];

        if ($cacheKeys === []) {
            return;
        }

        if ($this->forgetDatabaseKeys($cache, $cacheKeys)) {
            return;
        }

        foreach ($cacheKeys as $cacheKey) {
            $cache->forget($cacheKey);
        }
    }

    /**
     * @return list<string>
     */
    public static function cacheKeysForBranch(int $branchId): array
    {
        if ($branchId < 1) {
            return [];
        }

        return array_values(array_unique([
            ...GetGuestMenuForBranchAction::cacheKeysForBranch($branchId),
            GetBranchPollingIntervalAction::cacheKey($branchId),
        ]));
    }

    public static function cacheStore(): string
    {
        return 'database';
    }

    private function cache(): CacheRepository
    {
        return Cache::store(self::cacheStore());
    }

    /** @param list<string> $cacheKeys */
    private function forgetDatabaseKeys(CacheRepository $cache, array $cacheKeys): bool
    {
        if (! $cache instanceof Repository || $cache::class !== Repository::class) {
            return false;
        }

        $store = $cache->getStore();
        if (! $store instanceof DatabaseStore || $store::class !== DatabaseStore::class) {
            return false;
        }

        $events = $cache->getEventDispatcher();
        if ($events !== null && $events::class !== Dispatcher::class) {
            return false;
        }
        foreach ([ForgettingKey::class, KeyForgotten::class, KeyForgetFailed::class] as $event) {
            if ($events?->hasListeners($event)) {
                return false;
            }
        }

        $name = $cache->getName();
        $table = is_string($name) ? config('cache.stores.'.$name.'.table') : null;
        $connection = $store->getConnection();
        if (! is_string($table) || $table === '' || ! $connection instanceof Connection) {
            return false;
        }

        $keys = [];
        foreach ($cacheKeys as $cacheKey) {
            $keys[] = $store->getPrefix().$cacheKey;
            $keys[] = $store->getPrefix().Repository::FLEXIBLE_CREATED_KEY_PREFIX.$cacheKey;
        }

        (new DatabaseCacheEntry)
            ->setConnection($connection->getName())
            ->setTable($table)
            ->newQuery()
            ->whereKey(array_values(array_unique($keys)))
            ->delete();

        return true;
    }
}
