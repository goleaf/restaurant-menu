<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\SystemPermission;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\PermissionUserOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class PermissionQueryService
{
    /**
     * Resource policies remain authoritative when a capability is not sufficient.
     *
     * @param  list<string>  $codes
     * @return array<string, array{allowed: bool, source: 'superadmin'|'inactive_membership'|'scope_restricted'|'role'|'explicit_allow'|'explicit_deny'|'legacy'|'policy_allowed'|'policy_denied'}>
     */
    public function organizationAccessDecisions(User $user, Organization $organization, array $codes): array
    {
        $decisions = $user->organizationPermissionDecisions($organization, $codes);
        $abilities = [
            SystemPermission::ViewRestaurant->value => 'view',
            SystemPermission::EditRestaurant->value => 'update',
            SystemPermission::ManageBranches->value => 'manageBranches',
            SystemPermission::ManageStaff->value => 'manageStaff',
            SystemPermission::ManagePermissions->value => 'managePermissions',
        ];
        $gate = Gate::forUser($user);

        foreach (array_intersect_key($abilities, $decisions) as $code => $ability) {
            $response = $gate->inspect($ability, $organization);
            if ($response->allowed() !== $decisions[$code]['allowed']) {
                $decisions[$code] = [
                    'allowed' => $response->allowed(),
                    'source' => $response->allowed() ? 'policy_allowed' : 'policy_denied',
                ];
            }
        }

        return $decisions;
    }

    public function membership(Organization $organization, User $user): OrganizationUser
    {
        return OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'joined_at', 'invited_by_user_id', 'created_at', 'updated_at'])
            ->with(['role' => fn ($query) => $query->select(['id', 'code', 'name', 'sort_order'])])
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function permission(int $permissionId): Permission
    {
        return Permission::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($permissionId)
            ->firstOrFail();
    }

    /** @return Collection<int, bool> */
    public function roleDefaults(int $roleId): Collection
    {
        return PermissionRole::query()
            ->select(['permission_id', 'enabled'])
            ->where('role_id', $roleId)
            ->get()
            ->mapWithKeys(fn (PermissionRole $assignment): array => [
                $assignment->permission_id => $assignment->enabled,
            ]);
    }

    /** @return Collection<int, bool> */
    public function userOverrides(User $user, int $organizationId): Collection
    {
        return PermissionUserOverride::query()
            ->select(['permission_id', 'enabled'])
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('scope_key', 'organization:'.$organizationId)
            ->get()
            ->mapWithKeys(fn (PermissionUserOverride $override): array => [
                $override->permission_id => $override->enabled,
            ]);
    }

    public function hasLegacyOverrides(User $user): bool
    {
        return PermissionUserOverride::query()->where('user_id', $user->id)
            ->whereNull('organization_id')->where('scope_key', 'legacy')->exists();
    }

    /** @return EloquentCollection<int, Permission> */
    public function permissions(): EloquentCollection
    {
        return Permission::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->orderBy('sort_order')
            ->get();
    }
}
