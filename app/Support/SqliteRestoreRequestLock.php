<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class SqliteRestoreRequestLock
{
    /** @var resource|null */
    private mixed $handle = null;

    private bool $requestActive = false;

    private bool $exclusive = false;

    public function __construct(private readonly ?string $path = null) {}

    public function beginRequest(bool $exclusive = false): void
    {
        $this->acquire($exclusive ? LOCK_EX : LOCK_SH);
        $this->exclusive = $exclusive;
        $this->requestActive = true;
    }

    public function acquireExclusive(): void
    {
        if ($this->exclusive) {
            return;
        }
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
        }
        $this->acquire(LOCK_EX);
        $this->exclusive = true;
    }

    public function releaseOutsideRequest(): void
    {
        if (! $this->requestActive) {
            $this->endRequest();
        }
    }

    public function endRequest(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
        $this->requestActive = false;
        $this->exclusive = false;
    }

    public function markUnsafe(): void
    {
        if (file_put_contents($this->lockPath().'.blocked', "Recovery must be verified before requests resume.\n") === false) {
            throw new RuntimeException('Unable to preserve the SQLite recovery barrier.');
        }
    }

    public function markSafe(): void
    {
        if ($this->requiresRecovery() && ! unlink($this->lockPath().'.blocked')) {
            throw new RuntimeException('Unable to remove the verified SQLite recovery barrier.');
        }
    }

    public function requiresRecovery(): bool
    {
        clearstatcache(true, $this->lockPath().'.blocked');

        return is_file($this->lockPath().'.blocked');
    }

    private function lockPath(): string
    {
        return $this->path ?? storage_path('framework/sqlite-restore.lock');
    }

    private function acquire(int $operation): void
    {
        if (! is_resource($this->handle)) {
            $this->handle = fopen($this->lockPath(), 'c+');
        }
        if (! is_resource($this->handle)) {
            throw new RuntimeException('Unable to open the SQLite request coordination lock.');
        }
        $deadline = microtime(true) + 5;
        do {
            if (flock($this->handle, $operation | LOCK_NB)) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Timed out waiting for active SQLite requests to finish.');
    }
}
