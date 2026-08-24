<?php

namespace App\Actions\Brands;

use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteBrandAction
{
    public function handle(User $actor, Organization $organization, Brand $brand): void
    {
        DB::transaction(function () use ($actor, $organization, $brand): void {
            $scopedBrand = $organization->brands()
                ->select(['brands.id', 'brands.organization_id', 'brands.name'])
                ->whereKey($brand->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $scopedBrand);

            $hasActiveOrder = Order::query()
                ->active()
                ->whereIn('branch_id', Branch::withTrashed()
                    ->select('id')
                    ->where('organization_id', $organization->id)
                    ->where('brand_id', $scopedBrand->id))
                ->exists();

            if ($hasActiveOrder) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::StructureHasActiveOrder,
                    'structureDeletion',
                    context: [
                        'organization_id' => $organization->id,
                        'brand_id' => $scopedBrand->id,
                    ],
                );
            }

            $scopedBrand->deleteOrFail();
        });
    }
}
