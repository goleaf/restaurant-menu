<?php

declare(strict_types=1);

namespace App\Services\QrCodes;

use App\Enums\QrCodeStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Services\QrPrintBrandingResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final readonly class QrPrintQueryService
{
    public function __construct(private QrPrintBrandingResolver $brandingResolver) {}

    /** @return array{organization: Organization, brand: Brand, branch: Branch} */
    public function branchContext(Organization $organization, Brand $brand, Branch $branch): array
    {
        $organization = Organization::query()
            ->select($this->brandingResolver->columnsWithOptionalLogo(new Organization, ['id', 'owner_user_id', 'name']))
            ->whereKey($organization->id)
            ->firstOrFail();

        $brand = Brand::query()
            ->select($this->brandingResolver->columnsWithOptionalLogo(new Brand, ['id', 'organization_id', 'name']))
            ->whereKey($brand->id)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $branch = Branch::query()
            ->select($this->brandingResolver->columnsWithOptionalLogo(new Branch, [
                'id',
                'organization_id',
                'brand_id',
                'name',
                'address',
                'city',
                'country',
                'timezone',
                'currency',
                'is_active',
            ]))
            ->whereKey($branch->id)
            ->where('organization_id', $organization->id)
            ->where('brand_id', $brand->id)
            ->firstOrFail();

        return [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
        ];
    }

    /** @return array{organization: Organization, brand: Brand, branch: Branch, servicePoint: ServicePoint, qrCode: QrCode} */
    public function printContext(
        Organization $organization,
        Brand $brand,
        Branch $branch,
        ServicePoint $servicePoint,
        QrCode $qrCode,
    ): array {
        $branchContext = $this->branchContext($organization, $brand, $branch);
        $organization = $branchContext['organization'];
        $brand = $branchContext['brand'];
        $branch = $branchContext['branch'];

        $servicePoint = ServicePoint::query()
            ->select($this->servicePointColumns())
            ->whereKey($servicePoint->id)
            ->where('branch_id', $branch->id)
            ->firstOrFail();

        $qrCode = QrCode::query()
            ->select($this->qrCodeColumns())
            ->whereKey($qrCode->id)
            ->where('service_point_id', $servicePoint->id)
            ->firstOrFail();

        return [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ];
    }

    /** @return EloquentCollection<int, AreaNode> */
    public function areaNodes(Branch $branch): EloquentCollection
    {
        return $branch->areaNodes()
            ->select([
                'id',
                'branch_id',
                'parent_id',
                'type',
                'name',
                'icon',
                'sort_order',
                'is_active',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /** @return EloquentCollection<int, ServicePoint> */
    public function servicePoints(Branch $branch, string $areaNodeId): EloquentCollection
    {
        return $branch->servicePoints()
            ->select([
                ...$this->servicePointColumns(),
                'internal_code',
                'created_at',
                'updated_at',
            ])
            ->when(
                $areaNodeId !== 'all' && $areaNodeId !== 'none',
                fn ($query) => $query->where('area_node_id', (int) $areaNodeId),
            )
            ->when(
                $areaNodeId === 'none',
                fn ($query) => $query->whereNull('area_node_id'),
            )
            ->with([
                'areaNode' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'parent_id',
                    'type',
                    'name',
                    'icon',
                    'sort_order',
                    'is_active',
                ]),
                'activeQrCode' => fn ($query) => $query->select([
                    'id',
                    'service_point_id',
                    'public_token',
                    'short_code',
                    'status',
                    'created_at',
                ])->where('status', QrCodeStatus::Active->value),
            ])
            ->orderBy('area_node_id')
            ->orderBy('display_number')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function findBranchServicePoint(Branch $branch, int $servicePointId): ServicePoint
    {
        return $branch->servicePoints()
            ->select([
                ...$this->servicePointColumns(),
                'internal_code',
                'created_at',
                'updated_at',
            ])
            ->whereKey($servicePointId)
            ->firstOrFail();
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
}
