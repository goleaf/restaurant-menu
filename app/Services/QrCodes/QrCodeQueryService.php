<?php

declare(strict_types=1);

namespace App\Services\QrCodes;

use App\Actions\QrCodes\StoreQrCodeImageAction;
use App\Enums\QrCodeStatus;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Collection;
use App\Support\LocalizedDateFormatter;

final class QrCodeQueryService
{
    public function __construct(private readonly StoreQrCodeImageAction $images, private readonly Factory $filesystem) {}

    /** @return array{point:ServicePoint,qr:?QrCode,active_id:?int} */
    public function panel(Branch $branch, int $pointId, ?int $qrId = null): array
    {
        $point = ServicePoint::query()->select($this->servicePointColumns())->with('areaNode:id,branch_id,name')
            ->where('branch_id', $branch->id)->whereKey($pointId)->firstOrFail();
        $active = $point->qrCodes()->select($this->qrCodeColumns())->where('status', QrCodeStatus::Active)->first();
        $qr = $qrId === null ? ($active ?? $point->qrCodes()->select($this->qrCodeColumns())->latest('id')->first())
            : $point->qrCodes()->select($this->qrCodeColumns())->whereKey($qrId)->firstOrFail();

        return ['point' => $point, 'qr' => $qr, 'active_id' => $active?->id];
    }

    /** @param array{point:ServicePoint,qr:?QrCode,active_id:?int} $context @return array<string,mixed> */
    public function presentPanel(array $context): array
    {
        $qr = $context['qr'];
        $path = $qr === null ? null : $this->images->pathFor($qr);
        $disk = $this->filesystem->disk('public');

        return [
            'point_name' => $context['point']->name,
            'point_number' => $context['point']->display_number,
            'area_name' => $context['point']->areaNode?->name ?? __('qr.labels.no_zone'),
            'created_at' => $qr === null ? null : LocalizedDateFormatter::dateTime($qr->created_at),
            'short_code' => $qr?->short_code,
            'status_label' => $qr === null ? 'qr.labels.no_qr' : $qr->status->label(),
            'image_url' => $path !== null && $disk->exists($path) ? $disk->url($path) : null,
            'public_url' => $qr === null ? null : route('public.qr.show', ['token' => $qr->public_token]),
            'can_generate' => $qr === null,
            'can_disable' => $qr?->status === QrCodeStatus::Active,
            'can_repair' => $qr?->status === QrCodeStatus::Active && $qr->id === $context['active_id'],
            'can_reissue' => $qr !== null && $qr->status !== QrCodeStatus::Revoked,
            'has_other_current' => $context['active_id'] !== null && $context['active_id'] !== $qr?->id,
        ];
    }

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
            'structure_version',
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
            'structure_version',
            'is_active',
        ];
    }
}
