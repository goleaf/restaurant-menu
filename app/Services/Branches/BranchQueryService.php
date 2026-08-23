<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Enums\QrCodeStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Pagination\Paginator;

final class BranchQueryService
{
    /** @return Paginator<int, Branch> */
    public function paginateAccessibleForBrand(
        User $user,
        Organization $organization,
        Brand $brand,
        string $search,
        int $perPage,
    ): Paginator {
        $search = trim($search);

        return $brand->branches()
            ->select($this->columns())
            ->withCount([
                'areaNodes as setup_active_area_nodes_count' => fn ($query) => $query->where('is_active', true),
                'servicePoints as setup_active_service_points_count' => fn ($query) => $query->where('is_active', true),
                'servicePoints as setup_active_qr_codes_count' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('activeQrCode'),
            ])
            ->with([
                'servicePoints' => fn ($query) => $query
                    ->select([
                        'id',
                        'branch_id',
                        'name',
                        'is_active',
                    ])
                    ->where('is_active', true)
                    ->with([
                        'activeQrCode' => fn ($query) => $query
                            ->select([
                                'id',
                                'service_point_id',
                                'public_token',
                                'short_code',
                                'status',
                            ])
                            ->where('status', QrCodeStatus::Active->value),
                    ])
                    ->orderBy('id'),
            ])
            ->whereIn('id', $user->accessibleBranchIdsForOrganization($organization))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->simplePaginate($perPage, pageName: 'branchesPage');
    }

    public function findForBrand(Brand $brand, int $branchId): Branch
    {
        return $brand->branches()
            ->select($this->columns())
            ->whereKey($branchId)
            ->firstOrFail();
    }

    /** @return list<string> */
    private function columns(): array
    {
        return [
            'id',
            'organization_id',
            'brand_id',
            'name',
            'logo_path',
            'address',
            'city',
            'country',
            'timezone',
            'currency',
            'is_active',
            'created_at',
            'updated_at',
        ];
    }
}
