<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Organizations\UpdateOrganizationAction;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SaveOnboardingOrganizationAction
{
    public function __construct(
        private CreateOrganizationAction $createOrganization,
        private UpdateOrganizationAction $updateOrganization,
    ) {}

    /** @param array{name: string} $data */
    public function handle(User $user, ?int $onboardingId, array $data): RestaurantOnboarding
    {
        return DB::transaction(function () use ($user, $onboardingId, $data): RestaurantOnboarding {
            $onboarding = $this->resolveOnboarding($user, $onboardingId);
            Gate::forUser($user)->authorize('update', $onboarding);

            $organization = $onboarding->organization_id === null ? null : Organization::query()
                ->withTrashed()
                ->select(['id', 'owner_user_id', 'name', 'deleted_at'])
                ->where('owner_user_id', $user->id)
                ->whereKey($onboarding->organization_id)
                ->firstOrFail();

            if ($organization instanceof Organization) {
                if ($organization->trashed()) {
                    Gate::forUser($user)->authorize('restoreCheckpointResource', [$onboarding, $organization]);
                    $organization->restore();
                }

                Gate::forUser($user)->authorize('update', $organization);
                $this->updateOrganization->handle($organization, $data, actor: $user);
            } else {
                Gate::forUser($user)->authorize('create', Organization::class);
                $organization = $this->createOrganization->handle($user, $data);
                $onboarding->forceFill(['organization_id' => $organization->id])->save();
            }

            return $onboarding->refresh();
        }, attempts: 3);
    }

    private function resolveOnboarding(User $user, ?int $onboardingId): RestaurantOnboarding
    {
        if ($onboardingId !== null) {
            return RestaurantOnboarding::query()
                ->where('user_id', $user->id)
                ->whereKey($onboardingId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        throw ValidationException::withMessages(['creation' => __('center.explicit_creation')]);
    }
}
