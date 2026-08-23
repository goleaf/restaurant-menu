<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class BrandQueryService
{
    /** @return EloquentCollection<int, Brand> */
    public function forOrganization(Organization $organization): EloquentCollection
    {
        return $organization->brands()
            ->select($this->columns())
            ->orderBy('name')
            ->orderBy('id')
            ->get();
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
