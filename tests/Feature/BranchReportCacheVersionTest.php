<?php

declare(strict_types=1);

use App\Support\BranchReportCacheVersion;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

test('report version initialization adopts a competing atomic winner', function (): void {
    $cache = Cache::store('database');
    $competed = false;
    Event::listen(CacheMissed::class, function (CacheMissed $event) use ($cache, &$competed): void {
        if ($competed || ! str_starts_with($event->key, 'report-version:')) {
            return;
        }

        $competed = true;
        $cache->add($event->key, 'competing-generation', 86400);
    });

    $first = BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1]));

    expect($competed)->toBeTrue()
        ->and(BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1])))->toBe($first);
});

test('report version invalidation affects exactly its namespace and branch', function (): void {
    $cache = Cache::store('database');
    $combined = BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1, 2]));
    $otherBranch = BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([2]));
    $otherReport = BranchReportCacheVersion::fingerprint($cache, 'dashboard', collect([1, 2]));

    expect(BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([2, 0, 1, 2, -1])))->toBe($combined);
    BranchReportCacheVersion::invalidate($cache, 'analytics', 1);

    expect(BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1, 2])))->not->toBe($combined)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([2])))->toBe($otherBranch)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'dashboard', collect([1, 2])))->toBe($otherReport);
});

test('expired report versions are replaced instead of reusing a default generation', function (): void {
    $cache = Cache::store('database');
    $this->freezeTime();
    $first = BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1]));
    $this->travel(86399)->seconds();
    expect(BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1])))->toBe($first);
    $this->travel(2)->seconds();
    $next = BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1]));

    expect($next)->not->toBe($first)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1])))->toBe($next);
});

test('warm report versions use one cache query as the branch set grows', function (): void {
    $cache = Cache::store('database');
    $one = collect([1]);
    $many = collect(range(1, 20));
    BranchReportCacheVersion::fingerprint($cache, 'analytics', $many);

    expect(countDatabaseQueries(fn () => BranchReportCacheVersion::fingerprint($cache, 'analytics', $one)))->toBe(1)
        ->and(countDatabaseQueries(fn () => BranchReportCacheVersion::fingerprint($cache, 'analytics', $many)))->toBe(1);
});

test('invalid report version metadata cannot reuse a shared fallback', function (): void {
    $cache = Cache::store('database');
    $injected = false;
    Event::listen(CacheMissed::class, function (CacheMissed $event) use ($cache, &$injected): void {
        if ($injected || ! str_starts_with($event->key, 'report-version:')) {
            return;
        }

        $injected = true;
        $cache->add($event->key, '', 86400);
    });
    $first = BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1]));

    expect($injected)->toBeTrue()
        ->and(BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1])))->not->toBe($first);
});

test('empty report branch sets never read or invalidate cache metadata', function (): void {
    $cache = Cache::store('database');

    expect(countDatabaseQueries(function () use ($cache): void {
        BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([0, -1]));
        BranchReportCacheVersion::invalidate($cache, 'analytics', 0);
    }))->toBe(0);
});

test('rolled back source transactions preserve the previous report version', function (): void {
    $cache = Cache::store('database');
    $initial = BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1]));

    expect(fn () => DB::transaction(function () use ($cache): void {
        BranchReportCacheVersion::invalidate($cache, 'analytics', 1);

        throw new RuntimeException('Source mutation failed.');
    }))->toThrow(RuntimeException::class, 'Source mutation failed.');

    expect(BranchReportCacheVersion::fingerprint($cache, 'analytics', collect([1])))->toBe($initial);
});

test('concurrent processes initialize one shared report version', function (): void {
    $databasePath = tempnam(sys_get_temp_dir(), 'restaurant-report-version-');
    expect($databasePath)->toBeString();
    $connectionName = 'report_version_concurrency';
    $connection = config('database.connections.sqlite');
    $connection['database'] = $databasePath;

    try {
        config([
            "database.connections.{$connectionName}" => $connection,
            'cache.stores.report_version_test' => [
                'driver' => 'database',
                'connection' => $connectionName,
                'table' => 'cache',
            ],
        ]);
        DB::purge($connectionName);
        expect(Artisan::call('migrate', [
            '--database' => $connectionName,
            '--path' => ['database/migrations/0001_01_01_000001_create_cache_table.php'],
            '--force' => true,
        ]))->toBe(0);

        $results = Concurrency::driver('process')->run([
            reportVersionInitializationTask($connection, $connectionName, $databasePath, 1),
            reportVersionInitializationTask($connection, $connectionName, $databasePath, 2),
        ], 30);

        expect(array_unique($results))->toHaveCount(1)
            ->and(BranchReportCacheVersion::fingerprint(Cache::store('report_version_test'), 'analytics', collect([1])))
            ->toBe($results[0]);
    } finally {
        Cache::forgetDriver('report_version_test');
        DB::purge($connectionName);
        File::delete([
            $databasePath, $databasePath.'-wal', $databasePath.'-shm',
            $databasePath.'.ready-1', $databasePath.'.ready-2',
        ]);
    }
});

/** @param array<string, mixed> $connection */
function reportVersionInitializationTask(array $connection, string $connectionName, string $databasePath, int $worker): Closure
{
    return static function () use ($connection, $connectionName, $databasePath, $worker): string {
        config([
            "database.connections.{$connectionName}" => $connection,
            'cache.stores.report_version_test' => [
                'driver' => 'database',
                'connection' => $connectionName,
                'table' => 'cache',
            ],
        ]);
        DB::purge($connectionName);
        Event::listen(CacheMissed::class, static function (CacheMissed $event) use ($databasePath, $worker): void {
            if (! str_starts_with($event->key, 'report-version:')) {
                return;
            }

            File::put($databasePath.'.ready-'.$worker, 'ready');
            $deadline = hrtime(true) + 10_000_000_000;

            while (! File::exists($databasePath.'.ready-'.(3 - $worker))) {
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException('Report version initialization barrier timed out.');
                }

                usleep(10_000);
            }
        });

        return BranchReportCacheVersion::fingerprint(Cache::store('report_version_test'), 'analytics', collect([1]));
    };
}
