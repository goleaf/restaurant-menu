<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class RestaurantOnboardingPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RestaurantOnboarding $restaurantOnboarding): bool
    {
        if ((int) $restaurantOnboarding->user_id !== (int) $user->id) {
            return false;
        }

        if ($restaurantOnboarding->organization_id === null) {
            return $this->canStartOnboarding($user);
        }

        $organization = Organization::query()
            ->withTrashed()
            ->select(['id', 'owner_user_id', 'deleted_at'])
            ->whereKey($restaurantOnboarding->organization_id)
            ->where('owner_user_id', $user->id)
            ->first();

        if (! $organization instanceof Organization) {
            return false;
        }

        $organization->load('subscription:id,organization_id,status');
        $subscription = $organization->subscription;

        if (! $subscription instanceof OrganizationSubscription
            || $subscription->status !== OrganizationSubscriptionStatus::Active) {
            return false;
        }

        if ($restaurantOnboarding->branch_id !== null
            && ! $user->canAccessBranch(
                (int) $restaurantOnboarding->branch_id,
                $organization,
                withTrashed: true,
            )) {
            return false;
        }

        if ($organization->trashed()) {
            $hasActiveMembership = $user->organizationMemberships()
                ->where('organization_id', $organization->id)
                ->where('status', OrganizationUserStatus::Active->value)
                ->exists();

            return $hasActiveMembership;
        }

        return $user->canAccessOrganization($organization);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canStartOnboarding($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RestaurantOnboarding $restaurantOnboarding): bool
    {
        return $this->view($user, $restaurantOnboarding);
    }

    public function restoreCheckpointResource(User $user, RestaurantOnboarding $onboarding, Model $resource): bool
    {
        if (! $this->update($user, $onboarding)) {
            return false;
        }

        return match (true) {
            $resource instanceof Organization => (int) $resource->id === (int) $onboarding->organization_id
                && (int) $resource->owner_user_id === (int) $user->id,
            $resource instanceof Brand => (int) $resource->id === (int) $onboarding->brand_id
                && (int) $resource->organization_id === (int) $onboarding->organization_id,
            $resource instanceof Branch => (int) $resource->id === (int) $onboarding->branch_id
                && (int) $resource->organization_id === (int) $onboarding->organization_id
                && (int) $resource->brand_id === (int) $onboarding->brand_id,
            $resource instanceof AreaNode => (int) $resource->id === (int) $onboarding->area_node_id
                && (int) $resource->branch_id === (int) $onboarding->branch_id,
            $resource instanceof ServicePoint => $this->servicePointBelongsToCheckpoint($resource, $onboarding),
            $resource instanceof Menu => (int) $resource->id === (int) $onboarding->menu_id
                && (int) $resource->branch_id === (int) $onboarding->branch_id,
            $resource instanceof MenuCategory => (int) $resource->id === (int) $onboarding->menu_category_id
                && (int) $resource->menu_id === (int) $onboarding->menu_id,
            $resource instanceof MenuItem => (int) $resource->id === (int) $onboarding->menu_item_id
                && (int) $resource->menu_id === (int) $onboarding->menu_id
                && (int) $resource->category_id === (int) $onboarding->menu_category_id,
            default => false,
        };
    }

    private function canStartOnboarding(User $user): bool
    {
        if (! $user->exists || $user->organizationMemberships()->exists()) {
            return false;
        }

        return ! $user->roles()
            ->where('roles.code', '!=', SystemRole::Owner->value)
            ->exists();
    }

    private function servicePointBelongsToCheckpoint(ServicePoint $servicePoint, RestaurantOnboarding $onboarding): bool
    {
        if ((int) $servicePoint->branch_id !== (int) $onboarding->branch_id
            || ($servicePoint->area_node_id !== null
                && (int) $servicePoint->area_node_id !== (int) $onboarding->area_node_id)
            || ! $servicePoint->relationLoaded('pivot')) {
            return false;
        }

        $pivot = $servicePoint->getRelation('pivot');

        return $pivot instanceof Pivot
            && (int) $pivot->getAttribute('restaurant_onboarding_id') === (int) $onboarding->id;
    }
}
