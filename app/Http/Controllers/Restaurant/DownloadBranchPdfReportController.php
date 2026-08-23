<?php

declare(strict_types=1);

namespace App\Http\Controllers\Restaurant;

use App\Actions\Exports\BuildBranchPdfReportAction;
use App\Enums\DataExportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\DownloadBranchCsvExportRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Response;

final class DownloadBranchPdfReportController extends Controller
{
    public function __invoke(
        DownloadBranchCsvExportRequest $request,
        Branch $branch,
        string $export,
        BuildBranchPdfReportAction $buildBranchPdfReport,
    ): Response {
        $user = $request->user();
        $type = DataExportType::tryFrom($export);

        abort_unless($user instanceof User, 403);
        abort_unless($type instanceof DataExportType, 404);

        $pdf = $buildBranchPdfReport->handle(
            user: $user,
            branch: $branch,
            type: $type,
            startedAt: $request->exportStartedAt(),
            endedAt: $request->exportEndedAt(),
        );

        return response($pdf['contents'], 200, [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
