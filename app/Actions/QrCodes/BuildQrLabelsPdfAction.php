<?php

declare(strict_types=1);

namespace App\Actions\QrCodes;

use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodeSvgRenderer;
use App\Services\SecurePdfRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class BuildQrLabelsPdfAction
{
    public function __construct(
        private readonly QrCodeSvgRenderer $qrCodeSvgRenderer,
        private readonly SecurePdfRenderer $pdfRenderer,
    ) {}

    /**
     * @param  list<int>  $servicePointIds
     * @return array{contents: string, filename: string}
     */
    public function handle(
        User $user,
        Branch $branch,
        array $servicePointIds,
        QrLabelPreset $preset,
        bool $printTableNumber,
    ): array {
        Gate::forUser($user)->authorize('viewAny', [QrCode::class, $branch]);

        $servicePoints = ServicePoint::query()
            ->select(['id', 'branch_id', 'name', 'display_number'])
            ->where('branch_id', $branch->id)
            ->whereIn('id', $servicePointIds)
            ->with([
                'activeQrCode' => fn ($query) => $query
                    ->select(['id', 'service_point_id', 'public_token', 'short_code', 'status'])
                    ->where('status', QrCodeStatus::Active->value),
            ])
            ->orderBy('display_number')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if (
            $servicePoints->count() !== count($servicePointIds)
            || $servicePoints->contains(fn (ServicePoint $servicePoint): bool => ! $servicePoint->activeQrCode instanceof QrCode)
        ) {
            throw ValidationException::withMessages([
                'service_points' => __('qr.validation.pdf_active_required'),
            ]);
        }

        $items = $servicePoints
            ->map(function (ServicePoint $servicePoint): array {
                $qrCode = $servicePoint->activeQrCode;
                $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);
                $svg = $this->qrCodeSvgRenderer->render($publicUrl, 360);

                return [
                    'service_point_label' => $servicePoint->display_number ?: $servicePoint->name,
                    'short_code' => $qrCode->short_code,
                    'qr_image_data_uri' => 'data:image/svg+xml;base64,'.base64_encode($svg),
                ];
            })
            ->all();

        $html = view('pdf.qr-labels', [
            'branchName' => $branch->name,
            'rows' => array_chunk($items, 2),
            'printTableNumber' => $printTableNumber,
            'preset' => $preset->value,
        ])->render();

        return [
            'contents' => $this->pdfRenderer->render($html),
            'filename' => 'restaurant-menu-qr-branch-'.$branch->id.'-'.now()->format('Y-m-d-His').'.pdf',
        ];
    }
}
