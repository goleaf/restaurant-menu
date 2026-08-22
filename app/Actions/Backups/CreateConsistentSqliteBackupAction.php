<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CreateConsistentSqliteBackupAction
{
    public function __construct(
        private readonly ResolveSqliteBackupFileAction $resolveSqliteBackupFile,
        private readonly FilesystemManager $filesystem,
        private readonly Filesystem $files,
    ) {}

    public function handle(): string
    {
        if (! class_exists(\SQLite3::class)) {
            throw new RuntimeException('The SQLite3 extension is required to create a consistent backup.');
        }

        $sourcePath = $this->resolveSqliteBackupFile->handle();
        $disk = $this->filesystem->disk('local');
        $directory = 'backups/sqlite';
        $disk->makeDirectory($directory);
        $backupPath = $disk->path($directory.'/'.Str::uuid()->toString().'.sqlite');

        try {
            $source = new \SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $destination = new \SQLite3($backupPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $source->enableExceptions(true);
            $destination->enableExceptions(true);
            $source->busyTimeout(5000);
            $destination->busyTimeout(5000);

            if (! $source->backup($destination)) {
                throw new RuntimeException('SQLite could not create a consistent backup snapshot.');
            }

            $destination->close();
            $source->close();
            $this->files->chmod($backupPath, 0600);

            if (! $this->files->isFile($backupPath) || $this->files->size($backupPath) < 100) {
                throw new RuntimeException('The SQLite backup snapshot is invalid.');
            }

            return $backupPath;
        } catch (Throwable $exception) {
            $this->files->delete($backupPath);

            throw new RuntimeException('Unable to create a consistent SQLite backup snapshot.', previous: $exception);
        }
    }
}
