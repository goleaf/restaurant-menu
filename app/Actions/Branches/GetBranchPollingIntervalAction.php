<?php

namespace App\Actions\Branches;

use App\Models\BranchSetting;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class GetBranchPollingIntervalAction
{
    private const CACHE_SECONDS = 300;

    private const CACHE_STORE = 'database';

    public function handle(int $branchId): int
    {
        if ($branchId < 1) {
            return 1;
        }

        $interval = self::cache()->remember(
            self::cacheKey($branchId),
            self::CACHE_SECONDS,
            fn (): int => (int) BranchSetting::query()
                ->select(['branch_id', 'polling_interval_seconds'])
                ->where('branch_id', $branchId)
                ->value('polling_interval_seconds'),
        );

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
        return max(1, min(60, $intervalSeconds));
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
