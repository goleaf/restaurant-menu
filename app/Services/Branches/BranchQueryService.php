<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Enums\QrCodeStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        string $lifecycle = 'active',
        string $sort = 'name_asc',
    ): Paginator {
        $search = trim($search);

        $branches = $brand->branches();

        if ($lifecycle === 'archived') {
            $branches->onlyTrashed();
        }

        $branches
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
            ->whereIn(
                'id',
                $user->accessibleBranchIdsForOrganization($organization, $lifecycle === 'archived'),
            )
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%');
            }));

        $this->applySort($branches, $sort);

        return $branches
            ->simplePaginate($perPage, pageName: 'branchesPage');
    }

    public function findForBrand(Brand $brand, int $branchId, bool $withTrashed = false): Branch
    {
        return $brand->branches()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
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
            'deleted_at',
        ];
    }

    /** @param HasMany<Branch, Brand> $query */
    private function applySort(HasMany $query, string $sort): void
    {
        match ($sort) {
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderBy('name')->orderBy('id'),
        };
    }
}
