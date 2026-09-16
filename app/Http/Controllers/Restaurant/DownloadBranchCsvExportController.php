<?php

namespace App\Http\Controllers\Restaurant;

use App\Actions\Exports\StreamBranchCsvExportAction;
use App\Enums\DataExportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\DownloadBranchReportRequest;
use App\Models\Branch;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBranchCsvExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DownloadBranchReportRequest $request, Branch $branch, string $export, StreamBranchCsvExportAction $streamBranchCsvExport): StreamedResponse
    {
        $user = $request->user();
        $type = DataExportType::tryFrom($export);

        abort_unless($user instanceof User, 403);
        abort_unless($type instanceof DataExportType, 404);

        return $streamBranchCsvExport->handle(
            user: $user,
            branch: $branch,
            type: $type,
            startedAt: $request->period()->startedAt,
            endedAt: $request->period()->endedAt,
        );
    }
}
