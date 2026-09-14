<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BranchReportCacheVersion
{
    private const LIFETIME_SECONDS = 86400;

    /**
     * @param  Collection<int, covariant int>  $branchIds
     */
    public static function fingerprint(Repository $cache, string $namespace, Collection $branchIds): string
    {
        $keys = $branchIds
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->sort()
            ->map(fn (int $branchId): string => self::key($namespace, $branchId))
            ->values()
            ->all();

        if ($keys === []) {
            return hash('sha256', '');
        }

        $versions = $cache->many($keys);
        $signature = [];

        foreach ($keys as $key) {
            $version = $versions[$key];

            if (! is_string($version) || $version === '') {
                $candidate = (string) Str::uuid();
                $version = $cache->add($key, $candidate, self::LIFETIME_SECONDS)
                    ? $candidate
                    : $cache->get($key);

                if (! is_string($version) || $version === '') {
                    // A concurrent invalidation must never fall back to a reusable default version.
                    $version = $candidate;
                }
            }

            $signature[] = $key.':'.$version;
        }

        return hash('sha256', implode('|', $signature));
    }

    public static function invalidate(Repository $cache, string $namespace, int $branchId): void
    {
        if ($branchId > 0) {
            $cache->forget(self::key($namespace, $branchId));
        }
    }

    private static function key(string $namespace, int $branchId): string
    {
        return 'report-version:v1:'.$namespace.':branch:'.$branchId;
    }
}
