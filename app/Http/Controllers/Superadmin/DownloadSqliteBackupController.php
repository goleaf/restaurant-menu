<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Backups\CreateConsistentSqliteBackupAction;
use App\Enums\AuditLogAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadSqliteBackupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        CreateConsistentSqliteBackupAction $createSqliteBackup,
        RecordAuditLogAction $recordAuditLog,
    ): BinaryFileResponse {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isSuperadmin()) {
            abort(403);
        }

        $authorization = $request->session()->pull('sqlite_backup_download_authorization');

        if (! is_array($authorization)
            || (int) ($authorization['user_id'] ?? 0) !== $user->id
            || (int) ($authorization['issued_at'] ?? 0) < now()->subMinutes(5)->timestamp
            || trim((string) ($authorization['reason'] ?? '')) === '') {
            abort(403);
        }

        $reason = trim((string) $authorization['reason']);

        try {
            $path = $createSqliteBackup->handle();
        } catch (RuntimeException) {
            abort(404);
        }

        $filename = 'restaurant-menu-sqlite-backup-'.now()->format('Y-m-d-His').'.sqlite';

        $recordAuditLog->handle(
            action: AuditLogAction::BackupDownloaded,
            entityType: 'sqlite_backup',
            actorUser: $user,
            newValues: [
                'filename' => $filename,
                'reason' => $reason,
            ],
        );

        return response()->download($path, $filename, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Content-Type' => 'application/vnd.sqlite3',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }
}
