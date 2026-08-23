<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Exceptions\InvalidSqliteBackupException;
use App\Models\User;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Session\SessionManager;
use RuntimeException;
use Throwable;

final class RestoreSqliteBackupAction
{
    public function __construct(
        private readonly ResolveSqliteBackupFileAction $resolveSqliteBackupFile,
        private readonly PrepareSqliteRestoreCandidateAction $prepareRestoreCandidate,
        private readonly CreateConsistentSqliteBackupAction $createConsistentBackup,
        private readonly BuildSqliteSchemaFingerprintAction $buildSchemaFingerprint,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly DatabaseManager $database,
        private readonly CacheManager $cache,
        private readonly SessionManager $sessions,
        private readonly MaintenanceMode $maintenanceMode,
        private readonly Filesystem $files,
    ) {}

    /**
     * @return array{safety_backup_path: string}
     */
    public function handle(string $uploadedPath, User $actor, string $reason): array
    {
        $connectionName = (string) config('database.default');
        $livePath = $this->resolveSqliteBackupFile->handle();
        $candidate = $this->prepareRestoreCandidate->handle($uploadedPath, $connectionName);

        try {
            $lockProvider = $this->cache->store('file')->getStore();

            if (! $lockProvider instanceof LockProvider) {
                throw new RuntimeException('The configured restore lock store does not support atomic locks.');
            }

            $result = $lockProvider
                ->lock('sqlite-database-restore', 300)
                ->block(5, fn (): array => $this->restoreWhileLocked(
                    candidatePath: $candidate['path'],
                    candidateFingerprint: $candidate['schema_fingerprint'],
                    livePath: $livePath,
                    connectionName: $connectionName,
                    actor: $actor,
                    reason: $reason,
                ));

            return $result;
        } finally {
            $this->files->delete([
                $candidate['path'],
                $candidate['path'].'-wal',
                $candidate['path'].'-shm',
                $candidate['path'].'-journal',
            ]);
        }
    }

    /**
     * @return array{safety_backup_path: string}
     */
    private function restoreWhileLocked(
        string $candidatePath,
        string $candidateFingerprint,
        string $livePath,
        string $connectionName,
        User $actor,
        string $reason,
    ): array {
        $activatedMaintenanceMode = ! $this->maintenanceMode->active();
        $safetyBackupPath = null;
        $replacementStarted = false;

        if ($activatedMaintenanceMode) {
            $this->maintenanceMode->activate([
                'except' => [],
                'redirect' => null,
                'retry' => 60,
                'refresh' => null,
                'secret' => null,
                'status' => 503,
                'template' => null,
            ]);
        }

        try {
            $safetyBackupPath = $this->createConsistentBackup->handle();
            $this->database->purge($connectionName);
            $replacementStarted = true;
            $this->copyDatabase($candidatePath, $livePath);
            $this->database->purge($connectionName);

            $restoredFingerprint = $this->buildSchemaFingerprint->handle($connectionName);

            if (! hash_equals($candidateFingerprint, $restoredFingerprint)) {
                throw new InvalidSqliteBackupException('The restored database did not match the validated backup.');
            }

            $this->database->connection($connectionName)->transaction(function () use ($actor, $reason, $safetyBackupPath, $connectionName): void {
                User::on($connectionName)->newQuery()->update(['remember_token' => null]);

                $restoredActor = User::on($connectionName)
                    ->select(['id', 'email'])
                    ->whereKey($actor->id)
                    ->where('email', $actor->email)
                    ->first();

                if ($restoredActor instanceof User && ! $restoredActor->isSuperadmin()) {
                    $restoredActor = null;
                }

                $this->recordAuditLog->handle(
                    action: AuditLogAction::BackupRestored,
                    entityType: 'sqlite_backup',
                    actorUser: $restoredActor,
                    newValues: [
                        'initiated_by_user_id' => $actor->id,
                        'reason' => $reason,
                        'safety_snapshot' => basename($safetyBackupPath),
                    ],
                );
            });

            $this->sessions->driver()->getHandler()->gc(0);

            if ($this->defaultCacheUsesDatabase()) {
                $this->cache->store()->flush();
            }

            return ['safety_backup_path' => $safetyBackupPath];
        } catch (Throwable $restoreException) {
            $this->database->purge($connectionName);

            if (! $replacementStarted || ! is_string($safetyBackupPath)) {
                if ($restoreException instanceof RuntimeException) {
                    throw $restoreException;
                }

                throw new RuntimeException('SQLite restoration could not start and the live database was not changed.', previous: $restoreException);
            }

            try {
                $this->copyDatabase($safetyBackupPath, $livePath);
                $this->database->purge($connectionName);
            } catch (Throwable $rollbackException) {
                report($restoreException);

                throw new RuntimeException(
                    'SQLite restoration failed and the automatic rollback could not be completed.',
                    previous: $rollbackException,
                );
            }

            if ($restoreException instanceof RuntimeException) {
                throw $restoreException;
            }

            throw new RuntimeException('SQLite restoration failed and the live database was rolled back.', previous: $restoreException);
        } finally {
            $this->database->purge($connectionName);

            if ($activatedMaintenanceMode) {
                $this->maintenanceMode->deactivate();
            }
        }
    }

    private function copyDatabase(string $sourcePath, string $destinationPath): void
    {
        if (! class_exists(\SQLite3::class)) {
            throw new RuntimeException('The SQLite3 extension is required to restore a backup.');
        }

        $source = null;
        $destination = null;

        try {
            $source = new \SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $destination = new \SQLite3($destinationPath, SQLITE3_OPEN_READWRITE);
            $source->enableExceptions(true);
            $destination->enableExceptions(true);
            $source->busyTimeout(5000);
            $destination->busyTimeout(5000);

            if (! $source->backup($destination)) {
                throw new RuntimeException('SQLite could not restore the selected backup.');
            }
        } finally {
            $destination?->close();
            $source?->close();
        }
    }

    private function defaultCacheUsesDatabase(): bool
    {
        $defaultStore = (string) config('cache.default');

        return config("cache.stores.{$defaultStore}.driver") === 'database';
    }
}
