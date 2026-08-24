<?php

namespace App\Actions\Branches;

use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteBranchAction
{
    public function handle(User $actor, Organization $organization, Brand $brand, Branch $branch): void
    {
        DB::transaction(function () use ($actor, $organization, $brand, $branch): void {
            $scopedBranch = $brand->branches()
                ->select([
                    'branches.id',
                    'branches.organization_id',
                    'branches.brand_id',
                    'branches.name',
                ])
                ->where('organization_id', $organization->id)
                ->whereKey($branch->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $scopedBranch);

            if ($scopedBranch->orders()->active()->exists()) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::StructureHasActiveOrder,
                    'structureDeletion',
                    context: [
                        'organization_id' => $organization->id,
                        'brand_id' => $brand->id,
                        'branch_id' => $scopedBranch->id,
                    ],
                );
            }

            $scopedBranch->deleteOrFail();
        });
    }
}
