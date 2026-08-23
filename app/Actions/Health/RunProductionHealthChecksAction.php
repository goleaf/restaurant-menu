<?php

declare(strict_types=1);

namespace App\Actions\Health;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class RunProductionHealthChecksAction
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CacheManager $cache,
        private readonly FilesystemManager $filesystem,
        private readonly Filesystem $files,
    ) {}

    public function handle(): void
    {
        $checks = [
            'database' => fn (): bool => $this->databaseIsHealthy(),
            'cache' => fn (): bool => $this->cacheIsHealthy(),
            'private_storage' => fn (): bool => $this->privateStorageIsHealthy(),
            'logs' => fn (): bool => $this->logStorageIsHealthy(),
        ];

        foreach ($checks as $name => $check) {
            if (! (bool) config("monitoring.health.checks.{$name}", true)) {
                continue;
            }

            try {
                if (! $check()) {
                    throw new RuntimeException('The dependency returned an invalid health result.');
                }
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    "Production health check [{$name}] failed.",
                    previous: $exception,
                );
            }
        }
    }

    private function databaseIsHealthy(): bool
    {
        return $this->database
            ->connection()
            ->getSchemaBuilder()
            ->hasTable((string) config('database.migrations.table', 'migrations'));
    }

    private function cacheIsHealthy(): bool
    {
        $key = 'health-check:'.Str::uuid()->toString();
        $value = Str::random(32);
        $store = $this->cache->store();

        try {
            $store->put($key, $value, 10);
            $storedValue = $store->get($key);

            return is_string($storedValue) && hash_equals($value, $storedValue);
        } finally {
            $store->forget($key);
        }
    }

    private function privateStorageIsHealthy(): bool
    {
        $disk = $this->filesystem->disk('local');
        $path = 'health-checks/'.Str::uuid()->toString();
        $value = Str::random(32);

        try {
            if (! $disk->put($path, $value)) {
                return false;
            }

            $storedValue = $disk->get($path);

            return hash_equals($value, $storedValue);
        } finally {
            $disk->delete($path);
        }
    }

    private function logStorageIsHealthy(): bool
    {
        $directory = (string) config('monitoring.health.log_directory', storage_path('logs'));

        if (! $this->files->isDirectory($directory)) {
            return false;
        }

        $path = $directory.DIRECTORY_SEPARATOR.'health-check-'.Str::uuid()->toString();
        $value = Str::random(32);

        try {
            if ($this->files->put($path, $value, true) === false) {
                return false;
            }

            return hash_equals($value, $this->files->get($path));
        } finally {
            $this->files->delete($path);
        }
    }
}
