<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;

final class BrandPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->canAccessOrganization($organization);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->canAccessOrganization((int) $brand->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->canManageOrganizationBrands($organization);
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->view($user, $brand)
            && $user->canManageOrganizationBrands((int) $brand->organization_id);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->update($user, $brand);
    }

    public function restore(User $user, Brand $brand): bool
    {
        return $user->isSuperadmin();
    }

    public function forceDelete(User $user, Brand $brand): bool
    {
        return $user->isSuperadmin();
    }
}
