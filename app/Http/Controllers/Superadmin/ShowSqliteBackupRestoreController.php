<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\Backups\PrepareSqliteRestoreCandidateAction;
use App\Actions\Backups\ResolveSqliteRestoreAuthorizationAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ShowSqliteBackupRestoreController extends Controller
{
    public function __invoke(
        Request $request,
        ResolveSqliteRestoreAuthorizationAction $resolveAuthorization,
    ): View {
        $resolveAuthorization->handle($request);

        return view('superadmin.backups.restore-sqlite', [
            'maximumSizeMegabytes' => intdiv(PrepareSqliteRestoreCandidateAction::MAXIMUM_BYTES, 1024 * 1024),
        ]);
    }
}
