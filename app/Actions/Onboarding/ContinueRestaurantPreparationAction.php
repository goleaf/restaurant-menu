<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class ContinueRestaurantPreparationAction
{
    public function handle(User $actor, Branch $branch): RestaurantOnboarding
    {
        return DB::transaction(function () use ($actor, $branch): RestaurantOnboarding {
            $actor = User::query()->whereKey($actor->id)->firstOrFail();
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $branch);
            $organization = Organization::query()->whereKey($branch->organization_id)->firstOrFail();
            Gate::forUser($actor)->authorize('createAdditional', [RestaurantOnboarding::class, $organization]);
            Brand::query()->select(['id'])->where('organization_id', $organization->id)->whereKey($branch->brand_id)->firstOrFail();
            $existing = RestaurantOnboarding::query()->where('branch_id', $branch->id)->first();
            if ($existing !== null) {
                Gate::forUser($actor)->authorize('view', $existing);

                return $existing;
            }
            $setup = new RestaurantOnboarding;
            if ($setup->forceFill(['user_id' => $actor->id, 'organization_id' => $branch->organization_id, 'brand_id' => $branch->brand_id, 'branch_id' => $branch->id, 'purpose' => 'additional'])->save() !== true) {
                throw new \RuntimeException('The private preparation checkpoint could not be saved.');
            }

            return $setup;
        }, attempts: 3);
    }
}
