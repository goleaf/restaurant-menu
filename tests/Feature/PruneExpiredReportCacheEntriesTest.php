<?php

declare(strict_types=1);

use App\Actions\Analytics\PruneExpiredReportCacheEntriesAction;
use App\Models\DatabaseCacheEntry;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

test('report cache pruning removes unreachable expired snapshots and metadata only', function (): void {
    $this->freezeTime();
    $cache = Cache::store('database');
    $prefix = $cache->getStore()->getPrefix();
    $expiredKeys = [
        'analytics:dashboard:v3:orphan',
        'restaurant-dashboard:v4:orphan',
        Repository::FLEXIBLE_CREATED_KEY_PREFIX.'analytics:dashboard:v3:orphan',
        Repository::FLEXIBLE_CREATED_KEY_PREFIX.'restaurant-dashboard:v4:orphan',
        'analytics:dashboard:branch:1:keys',
        'report-version:v1:analytics:branch:1',
    ];

    foreach ($expiredKeys as $key) {
        DatabaseCacheEntry::factory()->create([
            'key' => $prefix.$key,
            'expiration' => now()->getTimestamp(),
        ]);
    }

    $live = DatabaseCacheEntry::factory()->create([
        'key' => $prefix.'analytics:dashboard:live',
        'expiration' => now()->addSecond()->getTimestamp(),
    ]);
    $unrelated = DatabaseCacheEntry::factory()->create([
        'key' => $prefix.'guest-menu:expired',
        'expiration' => now()->subMinute()->getTimestamp(),
    ]);
    $otherApplication = DatabaseCacheEntry::factory()->create([
        'key' => 'another-application:analytics:dashboard:orphan',
        'expiration' => now()->subMinute()->getTimestamp(),
    ]);

    expect(app(PruneExpiredReportCacheEntriesAction::class)->handle($cache))->toBe(count($expiredKeys))
        ->and(DatabaseCacheEntry::query()->whereKey(array_map(fn (string $key): string => $prefix.$key, $expiredKeys))->exists())->toBeFalse();
    $this->assertModelExists($live);
    $this->assertModelExists($unrelated);
    $this->assertModelExists($otherApplication);
    expect(app(PruneExpiredReportCacheEntriesAction::class)->handle($cache))->toBe(0);
});

test('report cache pruning limits each invocation to five hundred rows', function (): void {
    $this->freezeTime();
    DatabaseCacheEntry::factory()->count(503)->create(['expiration' => now()->subMinute()->getTimestamp()]);
    $action = app(PruneExpiredReportCacheEntriesAction::class);
    $cache = Cache::store('database');

    expect($action->handle($cache))->toBe(500)
        ->and(DatabaseCacheEntry::query()->where('expiration', '<=', now()->getTimestamp())->count())->toBe(3)
        ->and($action->handle($cache))->toBe(0);

    $this->travel(60)->seconds();

    expect($action->handle($cache))->toBe(3)
        ->and($action->handle($cache))->toBe(0);
});

test('report cache pruning retries after a failed cleanup instead of retaining its rate marker', function (): void {
    DatabaseCacheEntry::factory()->create(['expiration' => now()->subMinute()->getTimestamp()]);
    $cache = Cache::store('database');
    $familyPrefix = $cache->getStore()->getPrefix().'analytics:dashboard:';
    $failed = false;
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use ($familyPrefix, &$failed): void {
        if (! $failed && in_array($familyPrefix, $query->bindings, true)) {
            $failed = true;

            throw new RuntimeException('Pruning interrupted.');
        }
    });
    $action = app(PruneExpiredReportCacheEntriesAction::class);

    expect(fn () => $action->handle($cache))->toThrow(RuntimeException::class, 'Pruning interrupted.');
    expect($failed)->toBeTrue()
        ->and($cache->has('report-prune:v1'))->toBeFalse()
        ->and($action->handle($cache))->toBe(1);
});

test('report cache pruning preserves a snapshot renewed after candidate selection', function (): void {
    $this->freezeTime();
    $cache = Cache::store('database');
    $key = 'analytics:dashboard:renewed';
    DatabaseCacheEntry::factory()->create([
        'key' => $cache->getStore()->getPrefix().$key,
        'expiration' => now()->subMinute()->getTimestamp(),
    ]);
    $familyPrefix = $cache->getStore()->getPrefix().'analytics:dashboard:';
    $renewed = false;
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use ($cache, $key, $familyPrefix, &$renewed): void {
        if ($renewed || ! in_array($familyPrefix, $query->bindings, true)) {
            return;
        }

        $renewed = true;
        $cache->put($key, ['fresh' => true], 300);
    });

    expect(app(PruneExpiredReportCacheEntriesAction::class)->handle($cache))->toBe(0)
        ->and($renewed)->toBeTrue()
        ->and($cache->get($key))->toBe(['fresh' => true]);
});

test('report cache pruning follows its named cache connection table and literal prefix', function (): void {
    $connection = 'report_cache_pruning';
    config([
        "database.connections.{$connection}" => array_replace(config('database.connections.sqlite'), ['database' => ':memory:']),
        'cache.stores.report_pruning_test' => [
            'driver' => 'database',
            'connection' => $connection,
            'table' => 'report_cache',
            'prefix' => 'tenant_%:',
        ],
    ]);

    try {
        DB::purge($connection);
        Schema::connection($connection)->create('report_cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });
        $cache = Cache::store('report_pruning_test');
        $owned = DatabaseCacheEntry::factory()->connection($connection)->make([
            'key' => 'tenant_%:restaurant-dashboard:orphan',
            'expiration' => now()->subMinute()->getTimestamp(),
        ]);
        $owned->setTable('report_cache')->save();
        $otherPrefix = DatabaseCacheEntry::factory()->connection($connection)->make([
            'key' => 'tenant_X:restaurant-dashboard:orphan',
            'expiration' => now()->subMinute()->getTimestamp(),
        ]);
        $otherPrefix->setTable('report_cache')->save();
        $defaultEntry = DatabaseCacheEntry::factory()->create(['expiration' => now()->subMinute()->getTimestamp()]);

        expect(app(PruneExpiredReportCacheEntriesAction::class)->handle($cache))->toBe(1);
        $this->assertModelMissing($owned);
        $this->assertModelExists($otherPrefix);
        $this->assertModelExists($defaultEntry);
    } finally {
        Cache::forgetDriver('report_pruning_test');
        DB::purge($connection);
    }
});

test('report cache pruning ignores unsupported or unnamed cache stores', function (): void {
    $entry = DatabaseCacheEntry::factory()->create(['expiration' => now()->subMinute()->getTimestamp()]);
    $action = app(PruneExpiredReportCacheEntriesAction::class);

    expect($action->handle(Cache::store('array')))->toBe(0)
        ->and($action->handle(new Repository(new DatabaseStore(DB::connection(), 'cache'))))->toBe(0);
    $this->assertModelExists($entry);
});
