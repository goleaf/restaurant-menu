<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Exceptions\InvalidSqliteBackupException;
use App\Models\DatabaseSessionRecord;
use App\Models\McpAccessToken;
use App\Models\User;
use App\Support\SqliteRestoreRequestLock;
use Illuminate\Cache\CacheManager;
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
        private readonly SqliteRestoreRequestLock $requestLock,
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
            $result = $this->restoreWhileLocked(
                candidatePath: $candidate['path'],
                candidateFingerprint: $candidate['schema_fingerprint'],
                livePath: $livePath,
                connectionName: $connectionName,
                actor: $actor,
                reason: $reason,
            );

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
        $recoveryWasRequired = $this->requestLock->requiresRecovery();
        $safetyBackupPath = null;
        $replacementStarted = false;
        $safeToResume = false;

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
            $this->requestLock->acquireExclusive();
            $safeToResume = ! $recoveryWasRequired;
            if (! $this->maintenanceMode->active()) {
                $activatedMaintenanceMode = true;
                $this->maintenanceMode->activate(['status' => 503, 'retry' => 60]);
            }
            $safetyBackupPath = $this->createConsistentBackup->handle();
            $this->database->purge($connectionName);
            $this->requestLock->markUnsafe();
            $replacementStarted = true;
            $safeToResume = false;
            $this->copyDatabase($candidatePath, $livePath);
            $this->database->purge($connectionName);

            $restoredFingerprint = $this->buildSchemaFingerprint->handle($connectionName);

            if (! hash_equals($candidateFingerprint, $restoredFingerprint)) {
                throw new InvalidSqliteBackupException('The restored database did not match the validated backup.');
            }

            $this->database->connection($connectionName)->transaction(function () use ($actor, $reason, $safetyBackupPath, $connectionName): void {
                User::on($connectionName)->newQuery()->update(['remember_token' => null]);
                McpAccessToken::on($connectionName)->whereNull('revoked_at')->update(['revoked_at' => now()]);

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

            $this->invalidateRuntimeState();

            $safeToResume = true;

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
                $safeToResume = ! $recoveryWasRequired;
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
            try {
                $this->database->purge($connectionName);

                if ($safeToResume) {
                    $this->requestLock->markSafe();
                }

                if ($activatedMaintenanceMode && $safeToResume) {
                    $this->maintenanceMode->deactivate();
                }
            } finally {
                $this->requestLock->releaseOutsideRequest();
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

    private function invalidateRuntimeState(): void
    {
        $driver = config('session.driver');
        if ($driver === 'database') {
            $record = new DatabaseSessionRecord;
            $record->setConnection(config('session.connection') ?? config('database.default'));
            $record->setTable((string) config('session.table', 'sessions'));
            $record->newQuery()->delete();
        } elseif ($driver === 'file') {
            foreach ($this->files->files((string) config('session.files')) as $file) {
                if (! $this->files->delete($file->getPathname())) {
                    throw new RuntimeException('Unable to invalidate restored file sessions.');
                }
            }
        } elseif ($driver === 'array') {
            $this->sessions->driver()->getHandler()->gc(0);
        } else {
            throw new RuntimeException('The configured session driver does not support safe SQLite restoration.');
        }
        foreach (array_keys(config('cache.stores', [])) as $store) {
            if (config("cache.stores.{$store}.driver") === 'file'
                && ! $this->files->isDirectory((string) config("cache.stores.{$store}.path"))) {
                continue;
            }
            if (! $this->cache->store($store)->flush()) {
                throw new RuntimeException('Unable to invalidate the restored application cache.');
            }
        }
    }
}
