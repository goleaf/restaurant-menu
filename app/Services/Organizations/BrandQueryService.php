<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Pagination\Paginator;

final class BrandQueryService
{
    /** @return Paginator<int, Brand> */
    public function paginateForOrganization(Organization $organization, string $search, int $perPage): Paginator
    {
        $search = trim($search);

        return $organization->brands()
            ->select($this->columns())
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->simplePaginate($perPage, pageName: 'brandsPage');
    }

    public function findForOrganization(Organization $organization, int $brandId): Brand
    {
        return $organization->brands()
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
        ];
    }
}
