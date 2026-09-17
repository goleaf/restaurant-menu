<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Models\User;
use App\Support\Files\PreparedDownloadStore;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class PrepareBackupDownloadAction
{
    public function __construct(private CreateConsistentSqliteBackupAction $sqlite, private CreateMediaZipBackupAction $media, private RecordAuditLogAction $audit, private PreparedDownloadStore $downloads, private RequirePassword $password) {}

    public function handle(User $user, Request $request, string $kind, string $reason): string
    {
        abort_unless($user->isSuperadmin() && in_array($kind, ['sqlite', 'media'], true), 403);
        $confirmation = $this->password->handle($request, static fn (): Response => new Response(status: 204));
        abort_unless($confirmation instanceof Response && $confirmation->getStatusCode() === 204, 423);
        $backup = $kind === 'sqlite' ? ['path' => $this->sqlite->handle()] : $this->media->handle();
        $filename = 'restaurant-menu-'.$kind.'-backup-'.now()->format('Y-m-d-His').($kind === 'sqlite' ? '.sqlite' : '.zip');
        try {
            $this->audit->handle(
                action: $kind === 'sqlite' ? AuditLogAction::BackupDownloaded : AuditLogAction::MediaBackupDownloaded,
                entityType: $kind.'_backup',
                actorUser: $user,
                newValues: ['filename' => $filename, 'reason' => $reason, ...array_diff_key($backup, ['path' => true])],
            );

            return $this->downloads->issue($user, $backup['path'], $filename, $kind);
        } catch (Throwable $exception) {
            if (is_file($backup['path'])) {
                unlink($backup['path']);
            }
            throw $exception;
        }
    }
}
