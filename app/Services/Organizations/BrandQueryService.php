<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\Paginator;

final class BrandQueryService
{
    /** @return Paginator<int, Brand> */
    public function paginateForOrganization(
        Organization $organization,
        string $search,
        int $perPage,
        string $lifecycle = 'active',
        string $sort = 'name_asc',
    ): Paginator {
        $search = trim($search);

        $brands = $organization->brands();

        if ($lifecycle === 'archived') {
            $brands->onlyTrashed();
        }

        $brands
            ->select($this->columns())
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));

        $this->applySort($brands, $sort);

        return $brands
            ->simplePaginate($perPage, pageName: 'brandsPage');
    }

    public function findForOrganization(Organization $organization, int $brandId, bool $withTrashed = false): Brand
    {
        return $organization->brands()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->select($this->columns())
            ->whereKey($brandId)
            ->firstOrFail();
    }

    /** @return list<string> */
    private function columns(): array
    {
        return [
            'id',
            'organization_id',
            'name',
            'logo_path',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /** @param HasMany<Brand, Organization> $query */
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
