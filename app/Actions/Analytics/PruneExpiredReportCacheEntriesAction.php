<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Models\DatabaseCacheEntry;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\Connection;
use Throwable;

class PruneExpiredReportCacheEntriesAction
{
    private const RATE_KEY = 'report-prune:v1';

    public function handle(Repository $cache): int
    {
        $store = $cache->getStore();
        $name = $cache->getName();
        $table = is_string($name) ? config('cache.stores.'.$name.'.table') : null;

        if (! $store instanceof DatabaseStore || ! is_string($table) || $table === '') {
            return 0;
        }

        $connection = $store->getConnection();

        if (! $connection instanceof Connection) {
            return 0;
        }

        if (! $cache->add(self::RATE_KEY, true, 60)) {
            return 0;
        }

        try {
            return $this->prune($store, $connection, $table);
        } catch (Throwable $exception) {
            $cache->forget(self::RATE_KEY);

            throw $exception;
        }
    }

    private function prune(DatabaseStore $store, Connection $connection, string $table): int
    {
        $model = (new DatabaseCacheEntry)
            ->setConnection($connection->getName())
            ->setTable($table);
        $expiredAt = now()->getTimestamp();
        $keys = $model->newQuery()
            ->expiredReports($store->getPrefix(), $expiredAt)
            ->orderBy('expiration')
            ->orderBy('key')
            ->limit(500)
            ->modelKeys();

        if ($keys === []) {
            return 0;
        }

        return $model->newQuery()
            ->whereKey($keys)
            ->where('expiration', '<=', $expiredAt)
            ->delete();
    }
}
