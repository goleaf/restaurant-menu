<?php

namespace App\Http\Controllers\Superadmin;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Backups\ResolveSqliteBackupFileAction;
use App\Enums\AuditLogAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadSqliteBackupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ResolveSqliteBackupFileAction $resolveSqliteBackupFile,
        RecordAuditLogAction $recordAuditLog,
    ): BinaryFileResponse {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isSuperadmin()) {
            abort(403);
        }

        try {
            $path = $resolveSqliteBackupFile->handle();
        } catch (RuntimeException) {
            abort(404, 'SQLite database file is not available for backup download.');
        }

        $filename = 'restaurant-menu-sqlite-backup-'.now()->format('Y-m-d-His').'.sqlite';

        $recordAuditLog->handle(
            action: AuditLogAction::BackupDownloaded,
            entityType: 'sqlite_backup',
            actorUser: $user,
            newValues: [
                'filename' => $filename,
            ],
        );

        return response()->download($path, $filename, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Content-Type' => 'application/vnd.sqlite3',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
