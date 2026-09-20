<?php

namespace App\Actions\Branches;

use App\Models\BranchSetting;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class GetBranchPollingIntervalAction
{
    private const CACHE_SECONDS = 300;

    private const CACHE_STORE = 'database';

    public function handle(int $branchId): int
    {
        if ($branchId < 1) {
            return 1;
        }

        $cache = self::cache();
        $key = self::cacheKey($branchId);
        $interval = $cache->get($key);
        if (! is_int($interval)) {
            $read = fn (): int => (int) BranchSetting::query()
                ->select(['branch_id', 'polling_interval_seconds'])
                ->where('branch_id', $branchId)
                ->value('polling_interval_seconds');
            $interval = $cache instanceof Repository && $cache->getStore() instanceof DatabaseStore
                && $cache->getStore()->getConnection() === DB::connection()
                ? DB::transaction(fn (): mixed => $cache->remember($key, self::CACHE_SECONDS, $read), attempts: 3)
                : $read();
        }

        return self::normalize($interval);
    }

    public static function forgetForBranch(int $branchId): void
    {
        if ($branchId < 1) {
            return;
        }

        self::cache()->forget(self::cacheKey($branchId));
    }

    public static function normalize(int $intervalSeconds): int
    {
        return (int) Number::clamp($intervalSeconds, 1, 60);
    }

    public static function cacheKey(int $branchId): string
    {
        return 'branch-settings:polling-interval:'.$branchId;
    }

    private static function cache(): CacheRepository
    {
        return Cache::store(self::CACHE_STORE);
    }
}
