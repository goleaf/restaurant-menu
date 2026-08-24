<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreBrandAction
{
    public function handle(User $actor, Organization $organization, Brand $brand): void
    {
        DB::transaction(function () use ($actor, $organization, $brand): void {
            $scopedBrand = $organization->brands()
                ->withTrashed()
                ->select(['brands.id', 'brands.organization_id', 'brands.name', 'brands.deleted_at'])
                ->whereKey($brand->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('restore', $scopedBrand);
            $scopedBrand->restore();
        });
    }
}
