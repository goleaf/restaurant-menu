<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\User;

final class RestaurantSetupQueryService
{
    public function findOrganization(User $user, ?int $organizationId): Organization
    {
        if ($organizationId === null) {
            abort(403);
        }

        $organization = Organization::query()
            ->select(['id', 'owner_user_id', 'name'])
            ->whereKey($organizationId)
            ->firstOrFail();

        if (! $user->canAccessOrganization($organization)) {
            abort(403);
        }

        return $organization;
    }

    public function findBrand(Organization $organization, ?int $brandId): Brand
    {
        return $organization->brands()
            ->select(['id', 'organization_id', 'name'])
            ->whereKey($brandId)
            ->firstOrFail();
    }

    public function findBranch(Brand $brand, ?int $branchId): Branch
    {
        return $brand->branches()
            ->select([
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
            ])
            ->whereKey($branchId)
            ->firstOrFail();
    }

    public function findAreaNode(Branch $branch, ?int $areaNodeId): AreaNode
    {
        return $branch->areaNodes()
            ->select(['id', 'branch_id', 'name'])
            ->whereKey($areaNodeId)
            ->firstOrFail();
    }

    /**
     * @param  list<int>  $qrCodeIds
     * @return array{organization: Organization|null, brand: Brand|null, branch: Branch|null, areaNode: AreaNode|null, menu: Menu|null, qrCode: QrCode|null}
     */
    public function summaryContext(
        User $user,
        ?int $organizationId,
        ?int $brandId,
        ?int $branchId,
        ?int $areaNodeId,
        ?int $menuId,
        array $qrCodeIds,
    ): array {
        $organization = $organizationId === null ? null : Organization::query()
            ->select(['id', 'owner_user_id', 'name'])
            ->whereKey($organizationId)
            ->first();

        if ($organization instanceof Organization && ! $user->canAccessOrganization($organization)) {
            $organization = null;
        }

        $brand = $organization instanceof Organization && $brandId !== null ? Brand::query()
            ->select(['id', 'organization_id', 'name'])
            ->where('organization_id', $organization->id)
            ->whereKey($brandId)
            ->first() : null;
        $branch = $brand instanceof Brand && $branchId !== null ? Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name'])
            ->where('organization_id', $organization->id)
            ->where('brand_id', $brand->id)
            ->whereKey($branchId)
            ->first() : null;
        $areaNode = $branch instanceof Branch && $areaNodeId !== null ? AreaNode::query()
            ->select(['id', 'branch_id', 'name'])
            ->where('branch_id', $branch->id)
            ->whereKey($areaNodeId)
            ->first() : null;
        $menu = $branch instanceof Branch && $menuId !== null ? Menu::query()
            ->select(['id', 'branch_id', 'name'])
            ->where('branch_id', $branch->id)
            ->whereKey($menuId)
            ->first() : null;

        return [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'areaNode' => $areaNode,
            'menu' => $menu,
            'qrCode' => $this->firstQrCode($branch, $qrCodeIds),
        ];
    }

    /** @param list<int> $qrCodeIds */
    private function firstQrCode(?Branch $branch, array $qrCodeIds): ?QrCode
    {
        if ($qrCodeIds === [] || ! $branch instanceof Branch) {
            return null;
        }

        return QrCode::query()
            ->select(['id', 'service_point_id', 'public_token', 'short_code', 'status'])
            ->whereIn('id', $qrCodeIds)
            ->whereHas('servicePoint', function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id);
            })
            ->oldest('id')
            ->first();
    }
}
