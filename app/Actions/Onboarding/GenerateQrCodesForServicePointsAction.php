<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;

final readonly class GenerateQrCodesForServicePointsAction
{
    public function __construct(private GenerateQrCodeForServicePointAction $generateQrCode) {}

    /**
     * @param  list<int>  $servicePointIds
     * @return list<int>
     */
    public function handle(Branch $branch, array $servicePointIds, User $user): array
    {
        return ServicePoint::query()
            ->select(['id', 'branch_id'])
            ->whereIn('id', $servicePointIds)
            ->where('branch_id', $branch->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ServicePoint $servicePoint): int => $this->generateQrCode->handle($servicePoint, $user)->id)
            ->values()
            ->all();
    }
}
