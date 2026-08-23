<?php

declare(strict_types=1);

namespace App\Services\QrCodes;

use App\Models\QrCode;
use App\Models\ServicePoint;
use Illuminate\Support\Collection;

final class QrCodeQueryService
{
    public function reloadForServicePoint(QrCode $qrCode, ServicePoint $servicePoint): QrCode
    {
        return QrCode::query()
            ->select($this->qrCodeColumns())
            ->with([
                'servicePoint' => fn ($query) => $query
                    ->select($this->servicePointColumns())
                    ->with([
                        'areaNode' => fn ($query) => $query->select(['id', 'branch_id', 'name']),
                    ]),
            ])
            ->whereKey($qrCode->id)
            ->where('service_point_id', $servicePoint->id)
            ->firstOrFail();
    }

    /** @param Collection<int, int<1, max>> $accessibleBranchIds */
    public function findAccessibleByShortCode(string $shortCode, Collection $accessibleBranchIds): ?QrCode
    {
        return QrCode::query()
            ->select($this->qrCodeColumns())
            ->with([
                'servicePoint' => fn ($query) => $query
                    ->withTrashed()
                    ->select([...$this->servicePointColumns(), 'deleted_at'])
                    ->with([
                        'areaNode' => fn ($query) => $query
                            ->withTrashed()
                            ->select(['id', 'branch_id', 'name', 'deleted_at']),
                        'branch' => fn ($query) => $query
                            ->withTrashed()
                            ->select([
                                'id',
                                'organization_id',
                                'brand_id',
                                'name',
                                'city',
                                'country',
                                'deleted_at',
                            ])
                            ->with([
                                'organization' => fn ($query) => $query
                                    ->withTrashed()
                                    ->select(['id', 'name', 'deleted_at']),
                                'brand' => fn ($query) => $query
                                    ->withTrashed()
                                    ->select(['id', 'organization_id', 'name', 'deleted_at']),
                            ]),
                    ]),
            ])
            ->where('short_code', $shortCode)
            ->whereHas('servicePoint', function ($query) use ($accessibleBranchIds): void {
                $query->whereIn('branch_id', $accessibleBranchIds);
            })
            ->first();
    }

    /** @return list<string> */
    private function qrCodeColumns(): array
    {
        return [
            'id',
            'service_point_id',
            'public_token',
            'short_code',
            'status',
            'created_by_user_id',
            'revoked_at',
            'revoked_by_user_id',
            'created_at',
            'updated_at',
        ];
    }

    /** @return list<string> */
    private function servicePointColumns(): array
    {
        return [
            'id',
            'branch_id',
            'area_node_id',
            'type',
            'name',
            'display_number',
            'capacity',
            'icon',
            'status',
            'is_active',
        ];
    }
}
