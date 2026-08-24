<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;

final class BranchPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->canAccessOrganization($organization);
    }

    public function view(User $user, Branch $branch): bool
    {
        return ! $branch->trashed()
            && $user->canAccessBranch($branch, (int) $branch->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->canManageOrganizationBranches($organization);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->view($user, $branch)
            && $user->canManageOrganizationBranches((int) $branch->organization_id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $branch->trashed()
            && $user->canAccessBranch($branch, (int) $branch->organization_id, true)
            && $user->canManageOrganizationBranches((int) $branch->organization_id);
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return $user->isSuperadmin();
    }

    public function manageSettings(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch)
            || $this->hasPermission($user, $branch, SystemPermission::ManageSettings);
    }

    public function manageZones(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::ManageZones);
    }

    public function manageServicePoints(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::ManageServicePoints);
    }

    public function changeServicePointStatus(User $user, Branch $branch): bool
    {
        return $this->manageServicePoints($user, $branch)
            || ($this->view($user, $branch)
                && $user->hasOrganizationRole((int) $branch->organization_id, SystemRole::Waiter));
    }

    public function manageMenu(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::ManageMenu);
    }

    public function changeMenuPrices(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::ChangePrices);
    }

    public function changeMenuAvailability(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::ChangeAvailability);
    }

    public function generateQr(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::GenerateQr);
    }

    public function manageStaff(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::ManageStaff);
    }

    public function openTable(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::ViewOrders)
            || $this->hasPermission($user, $branch, SystemPermission::ConfirmOrders);
    }

    public function closeTable(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::CloseTableSessions);
    }

    public function export(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, $branch, SystemPermission::ExportData);
    }

    private function hasPermission(User $user, Branch $branch, SystemPermission $permission): bool
    {
        return $this->view($user, $branch)
            && $user->hasPermission($permission, (int) $branch->organization_id);
    }
}
