<?php

namespace App\Actions\Branches;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use Illuminate\Cache\Repository as CacheRepository;
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

    public function handle(int $branchId): void
    {
        if ($branchId < 1) {
            return;
        }

        $cache = $this->cache();

        foreach (self::cacheKeysForBranch($branchId) as $cacheKey) {
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
}
