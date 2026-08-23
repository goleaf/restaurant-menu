<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Backups\CreateMediaZipBackupAction;
use App\Actions\Backups\ResolveMediaBackupAuthorizationAction;
use App\Enums\AuditLogAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadMediaBackupController extends Controller
{
    public function __invoke(
        Request $request,
        CreateMediaZipBackupAction $createMediaZipBackup,
        ResolveMediaBackupAuthorizationAction $resolveAuthorization,
        RecordAuditLogAction $recordAuditLog,
    ): BinaryFileResponse {
        $authorization = $resolveAuthorization->handle($request);

        try {
            $backup = $createMediaZipBackup->handle();
        } catch (RuntimeException) {
            abort(503, __('ui.superadmin.media_backup.failed'));
        }

        $filename = 'restaurant-menu-media-backup-'.now()->format('Y-m-d-His').'.zip';

        $recordAuditLog->handle(
            action: AuditLogAction::MediaBackupDownloaded,
            entityType: 'media_backup',
            actorUser: $authorization['user'],
            newValues: [
                'filename' => $filename,
                'reason' => $authorization['reason'],
                'file_count' => $backup['file_count'],
                'total_bytes' => $backup['total_bytes'],
                'sha256' => $backup['sha256'],
            ],
        );

        $response = response()->download($backup['path'], $filename, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
