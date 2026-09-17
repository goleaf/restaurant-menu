<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\Backups\RestoreSqliteBackupAction;
use App\Exceptions\InvalidSqliteBackupException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\RestoreSqliteBackupRequest;
use App\Models\User;
use App\Support\Backups\PreparedRestoreStore;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class RestoreSqliteBackupController extends Controller
{
    public function __invoke(
        RestoreSqliteBackupRequest $request,
        PreparedRestoreStore $candidates,
        RestoreSqliteBackupAction $restoreSqliteBackup,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $candidate = $candidates->consume($request, (string) $request->validated('grant'));

        try {
            $restoreSqliteBackup->handle(
                uploadedPath: $candidate['path'],
                actor: $user,
                reason: $candidate['reason'],
            );
        } catch (InvalidSqliteBackupException) {
            return $this->failureRedirect('ui.superadmin.backup_restore.incompatible');
        } catch (LockTimeoutException|RuntimeException $exception) {
            report($exception);

            return $this->failureRedirect('ui.superadmin.backup_restore.failed');
        } finally {
            if (is_file($candidate['path'])) {
                unlink($candidate['path']);
            }
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
