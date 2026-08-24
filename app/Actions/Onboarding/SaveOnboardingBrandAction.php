<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Brands\CreateBrandAction;
use App\Actions\Brands\UpdateBrandAction;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SaveOnboardingBrandAction
{
    public function __construct(private CreateBrandAction $createBrand, private UpdateBrandAction $updateBrand) {}

    /** @param array{name: string} $data */
    public function handle(User $user, int $onboardingId, array $data): RestaurantOnboarding
    {
        return DB::transaction(function () use ($user, $onboardingId, $data): RestaurantOnboarding {
            $onboarding = $this->onboarding($user, $onboardingId);
            $organization = Organization::query()
                ->select(['id', 'owner_user_id', 'name'])
                ->where('owner_user_id', $user->id)
                ->whereKey($onboarding->organization_id)
                ->firstOrFail();
            $brand = $onboarding->brand_id === null ? null : $organization->brands()
                ->withTrashed()
                ->select(['id', 'organization_id', 'name', 'deleted_at'])
                ->whereKey($onboarding->brand_id)
                ->firstOrFail();

            if ($brand instanceof Brand) {
                if ($brand->trashed()) {
                    Gate::forUser($user)->authorize('restoreCheckpointResource', [$onboarding, $brand]);
                    $brand->restore();
                }

                Gate::forUser($user)->authorize('update', $brand);
                $this->updateBrand->handle($brand, $data);
            } else {
                Gate::forUser($user)->authorize('create', [Brand::class, $organization]);
                $brand = $this->createBrand->handle($organization, $data);
                $onboarding->servicePoints()->detach();
                $onboarding->forceFill([
                    'brand_id' => $brand->id,
                    'branch_id' => null,
                    'area_node_id' => null,
                    'menu_id' => null,
                    'menu_category_id' => null,
                    'menu_item_id' => null,
                ])->save();
            }

            return $onboarding->refresh();
        }, attempts: 3);
    }

    private function onboarding(User $user, int $id): RestaurantOnboarding
    {
        $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->whereKey($id)->lockForUpdate()->firstOrFail();
        Gate::forUser($user)->authorize('update', $onboarding);

        return $onboarding;
    }
}
