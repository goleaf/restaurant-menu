<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreBranchAction
{
    public function handle(User $actor, Organization $organization, Brand $brand, Branch $branch): void
    {
        DB::transaction(function () use ($actor, $organization, $brand, $branch): void {
            $scopedBranch = $brand->branches()
                ->withTrashed()
                ->select([
                    'branches.id',
                    'branches.organization_id',
                    'branches.brand_id',
                    'branches.name',
                    'branches.deleted_at',
                ])
                ->where('organization_id', $organization->id)
                ->whereKey($branch->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('restore', $scopedBranch);
            $scopedBranch->restore();
        });
    }
}
