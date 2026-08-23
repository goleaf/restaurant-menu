<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizations;

use App\Actions\QrCodes\BuildQrLabelsPdfAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DownloadBranchQrPdfRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Response;

final class DownloadBranchQrPdfController extends Controller
{
    public function __invoke(
        DownloadBranchQrPdfRequest $request,
        Organization $organization,
        Brand $brand,
        Branch $branch,
        BuildQrLabelsPdfAction $buildQrLabelsPdf,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $pdf = $buildQrLabelsPdf->handle(
            user: $user,
            branch: $branch,
            servicePointIds: $request->servicePointIds(),
            preset: $request->preset(),
            printTableNumber: $request->shouldPrintTableNumber(),
        );

        return response($pdf['contents'], 200, [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
