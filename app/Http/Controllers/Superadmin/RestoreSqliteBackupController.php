<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\Backups\ResolveSqliteRestoreAuthorizationAction;
use App\Actions\Backups\RestoreSqliteBackupAction;
use App\Exceptions\InvalidSqliteBackupException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\RestoreSqliteBackupRequest;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class RestoreSqliteBackupController extends Controller
{
    public function __invoke(
        RestoreSqliteBackupRequest $request,
        ResolveSqliteRestoreAuthorizationAction $resolveAuthorization,
        RestoreSqliteBackupAction $restoreSqliteBackup,
    ): RedirectResponse {
        $authorization = $resolveAuthorization->handle($request, consume: true);
        $user = $request->user();
        $backup = $request->file('backup');

        if (! $user instanceof User || ! $backup instanceof UploadedFile) {
            abort(403);
        }

        $uploadedPath = $backup->getRealPath();

        if (! is_string($uploadedPath)) {
            return $this->failureRedirect('ui.superadmin.backup_restore.invalid_file');
        }

        try {
            $restoreSqliteBackup->handle(
                uploadedPath: $uploadedPath,
                actor: $user,
                reason: $authorization['reason'],
            );
        } catch (InvalidSqliteBackupException) {
            return $this->failureRedirect('ui.superadmin.backup_restore.incompatible');
        } catch (LockTimeoutException|RuntimeException $exception) {
            report($exception);

            return $this->failureRedirect('ui.superadmin.backup_restore.failed');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('status', __('ui.superadmin.backup_restore.completed'));
        Auth::forgetGuards();

        return redirect()->route('login');
    }

    private function failureRedirect(string $translationKey): RedirectResponse
    {
        return redirect()
            ->route('superadmin.dashboard')
            ->with('sqlite_backup_restore_error', __($translationKey));
    }
}
