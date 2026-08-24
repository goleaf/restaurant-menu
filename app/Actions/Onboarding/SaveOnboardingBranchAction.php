<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\UpdateBranchAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SaveOnboardingBranchAction
{
    public function __construct(private CreateBranchAction $createBranch, private UpdateBranchAction $updateBranch) {}

    /** @param array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool} $data */
    public function handle(User $user, int $onboardingId, array $data): RestaurantOnboarding
    {
        return DB::transaction(function () use ($user, $onboardingId, $data): RestaurantOnboarding {
            $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->whereKey($onboardingId)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $onboarding);
            $organization = Organization::query()->select(['id', 'owner_user_id', 'name'])->where('owner_user_id', $user->id)->whereKey($onboarding->organization_id)->firstOrFail();
            $brand = Brand::query()->select(['id', 'organization_id', 'name'])->where('organization_id', $organization->id)->whereKey($onboarding->brand_id)->firstOrFail();
            $branch = $onboarding->branch_id === null ? null : $brand->branches()
                ->withTrashed()
                ->select(['id', 'organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active', 'deleted_at'])
                ->whereKey($onboarding->branch_id)->firstOrFail();

            if ($branch instanceof Branch) {
                if ($branch->trashed()) {
                    Gate::forUser($user)->authorize('restoreCheckpointResource', [$onboarding, $branch]);
                    $branch->restore();
                }

                Gate::forUser($user)->authorize('update', $branch);
                $this->updateBranch->handle($branch, $data, $user);
            } else {
                Gate::forUser($user)->authorize('create', [Branch::class, $organization]);
                $branch = $this->createBranch->handle($brand, $data);
                $onboarding->forceFill(['branch_id' => $branch->id])->save();
            }

            return $onboarding->refresh();
        }, attempts: 3);
    }
}
