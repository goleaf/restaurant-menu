<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\User;

final class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $user->organizationMemberships()->exists();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->canAccessOrganization($organization);
    }

    public function create(User $user): bool
    {
        return $user->exists;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $user->hasOrganizationRole($organization, SystemRole::Owner);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }

    public function restore(User $user, Organization $organization): bool
    {
        return $user->isSuperadmin();
    }

    public function forceDelete(User $user, Organization $organization): bool
    {
        return $user->isSuperadmin();
    }

    public function manageBrands(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $user->canManageOrganizationBrands($organization);
    }

    public function manageBranches(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $user->canManageOrganizationBranches($organization);
    }

    public function manageStaff(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $user->hasPermission(SystemPermission::ManageStaff, $organization);
    }

    public function managePermissions(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $user->hasPermission(SystemPermission::ManagePermissions, $organization);
    }
}
