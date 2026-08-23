<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Exceptions\InvalidSqliteBackupException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SplFileObject;
use Throwable;

final class PrepareSqliteRestoreCandidateAction
{
    public const MAXIMUM_BYTES = 268_435_456;

    public function __construct(
        private readonly BuildSqliteSchemaFingerprintAction $buildSchemaFingerprint,
        private readonly DatabaseManager $database,
        private readonly FilesystemManager $filesystem,
        private readonly Filesystem $files,
    ) {}

    /**
     * @return array{path: string, schema_fingerprint: string}
     */
    public function handle(string $uploadedPath, string $liveConnectionName): array
    {
        $sourcePath = realpath($uploadedPath);

        if (! is_string($sourcePath)
            || ! $this->files->isFile($sourcePath)
            || ! $this->files->isReadable($sourcePath)
            || $this->files->size($sourcePath) < 100
            || $this->files->size($sourcePath) > self::MAXIMUM_BYTES
            || $this->magicHeader($sourcePath) !== "SQLite format 3\0") {
            throw new InvalidSqliteBackupException('The uploaded file is not a valid SQLite 3 database.');
        }

        if (! class_exists(\SQLite3::class)) {
            throw new InvalidSqliteBackupException('The SQLite3 extension is required to validate a restore backup.');
        }

        $disk = $this->filesystem->disk('local');
        $directory = 'backups/sqlite/restore-candidates';
        $disk->makeDirectory($directory);
        $candidatePath = $disk->path($directory.'/'.Str::uuid()->toString().'.sqlite');
        $candidateConnection = 'sqlite_restore_candidate_'.str_replace('-', '_', Str::uuid()->toString());
        $keepCandidate = false;

        try {
            $this->copyDatabase($sourcePath, $candidatePath);
            $this->files->chmod($candidatePath, 0600);

            $liveConfig = config("database.connections.{$liveConnectionName}");

            if (! is_array($liveConfig) || ($liveConfig['driver'] ?? null) !== 'sqlite') {
                throw new InvalidSqliteBackupException('The active database connection is not SQLite.');
            }

            config()->set("database.connections.{$candidateConnection}", [
                ...Arr::except($liveConfig, [
                    'url',
                    'pragmas',
                    'foreign_key_constraints',
                    'journal_mode',
                    'synchronous',
                    'transaction_mode',
                ]),
                'database' => $candidatePath,
            ]);

            $liveFingerprint = $this->buildSchemaFingerprint->handle($liveConnectionName);
            $candidateFingerprint = $this->buildSchemaFingerprint->handle($candidateConnection);

            if (! hash_equals($liveFingerprint, $candidateFingerprint)) {
                throw new InvalidSqliteBackupException('The SQLite backup schema is not compatible with this application release.');
            }

            $keepCandidate = true;

            return [
                'path' => $candidatePath,
                'schema_fingerprint' => $candidateFingerprint,
            ];
        } catch (InvalidSqliteBackupException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidSqliteBackupException(
                'The SQLite backup could not be read or validated.',
                previous: $exception,
            );
        } finally {
            $this->database->purge($candidateConnection);
            config()->set("database.connections.{$candidateConnection}", null);
            $this->files->delete([
                $candidatePath.'-wal',
                $candidatePath.'-shm',
                $candidatePath.'-journal',
            ]);

            if (! $keepCandidate) {
                $this->files->delete($candidatePath);
            }
        }
    }

    private function magicHeader(string $path): string
    {
        $file = new SplFileObject($path, 'rb');

        return $file->fread(16);
    }

    private function copyDatabase(string $sourcePath, string $destinationPath): void
    {
        $source = null;
        $destination = null;

        try {
            $source = new \SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $destination = new \SQLite3($destinationPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $source->enableExceptions(true);
            $destination->enableExceptions(true);
            $source->busyTimeout(5000);
            $destination->busyTimeout(5000);

            if (! $source->backup($destination)) {
                throw new InvalidSqliteBackupException('SQLite could not normalize the restore candidate.');
            }
        } finally {
            $destination?->close();
            $source?->close();
        }
    }
}
