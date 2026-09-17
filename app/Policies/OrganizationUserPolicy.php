<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;

final class OrganizationUserPolicy
{
    public function __construct(
        private readonly OrganizationPolicy $organizations,
        private readonly RolePolicy $roles,
    ) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->organizations->view($user, $organization);
    }

    public function view(User $user, OrganizationUser $membership): bool
    {
        if ((int) $membership->user_id === (int) $user->id) {
            return $this->withOrganization($membership, fn (Organization $organization): bool => $this->organizations->view($user, $organization));
        }

        return $this->withOrganization($membership, fn (Organization $organization): bool => $this->organizations->manageStaff($user, $organization));
    }

    public function viewCard(User $user, OrganizationUser $membership, ?Branch $branch = null): bool
    {
        if ($branch !== null) {
            return $branch->organization_id === $membership->organization_id
                && ! $branch->trashed() && $user->canAccessBranch($branch)
                && ($user->hasPermission(SystemPermission::ManageStaff, $membership->organization_id)
                    || $user->hasPermission(SystemPermission::ManagePermissions, $membership->organization_id));
        }

        return $this->withOrganization($membership, fn (Organization $organization): bool => $this->organizations->viewTeam($user, $organization));
    }

    public function viewHistory(User $user, OrganizationUser $membership, ?Branch $branch = null): bool
    {
        return $this->viewCard($user, $membership, $branch)
            && $user->hasPermission(SystemPermission::ViewAuditLog, $membership->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->organizations->manageStaff($user, $organization);
    }

    public function update(User $user, OrganizationUser $membership): bool
    {
        return $this->withOrganization($membership, fn (Organization $organization): bool => $this->organizations->manageStaff($user, $organization));
    }

    public function delete(User $user, OrganizationUser $membership): bool
    {
        return $this->update($user, $membership);
    }

    public function deactivate(User $user, OrganizationUser $membership): bool
    {
        return $this->update($user, $membership);
    }

    public function assignBranches(User $user, OrganizationUser $membership): bool
    {
        return $this->update($user, $membership);
    }

    public function managePermissions(User $user, OrganizationUser $membership): bool
    {
        if ((int) $membership->user_id === (int) $user->id) {
            return false;
        }

        return $this->withOrganization($membership, function (Organization $organization) use ($user, $membership): bool {
            if (! $this->organizations->managePermissions($user, $organization)) {
                return false;
            }

            $role = Role::query()
                ->select(['id', 'code', 'name', 'sort_order'])
                ->whereKey($membership->role_id)
                ->first();

            return $role instanceof Role
                && $this->roles->managePermissions($user, $role, $organization);
        });
    }

    /**
     * @param  callable(Organization): bool  $authorize
     */
    private function withOrganization(OrganizationUser $membership, callable $authorize): bool
    {
        $organization = $membership->organization;

        return $organization instanceof Organization && $authorize($organization);
    }
}
