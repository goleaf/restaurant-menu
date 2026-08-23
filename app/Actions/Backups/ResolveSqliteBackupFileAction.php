<?php

namespace App\Actions\Backups;

use RuntimeException;

final class ResolveSqliteBackupFileAction
{
    public function handle(): string
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'sqlite') {
            throw new RuntimeException('SQLite backup is available only when the sqlite connection is active.');
        }

        $database = trim((string) ($connection['database'] ?? ''));

        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('SQLite database file is not configured.');
        }

        $path = $this->resolveDatabasePath($database);
        $realPath = realpath($path);

        if (! is_string($realPath) || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new RuntimeException('SQLite database file is not readable.');
        }

        return $realPath;
    }

    private function resolveDatabasePath(string $database): string
    {
        if ($this->isAbsolutePath($database)) {
            return $database;
        }

        return base_path($database);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
