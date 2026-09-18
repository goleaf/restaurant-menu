<?php

namespace App\Actions\Waiter;

use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

class ResolveWaiterAccessibleBranchIdsAction
{
    /**
     * @param  list<SystemPermission>  $permissions
     * @return array<string, Collection<int, int>>
     */
    public function handleMany(User $user, array $permissions, bool $includeArchived = false): array
    {
        $codes = array_map(fn (SystemPermission $permission): string => $permission->value, $permissions);
        $singleCode = count($codes) === 1 ? $codes[0] : null;
        if ($user->isSuperadmin()) {
            $branches = $this->branchQuery($includeArchived)->select(['id'])->orderBy('id')->pluck('id');

            return array_fill_keys($codes, $branches);
        }

        $available = Permission::query()->select(['id', 'code'])->whereIn('code', $codes)->get()->keyBy('code');
        $overrides = PermissionUserOverride::query()->select(['id', 'permission_id', 'organization_id', 'scope_key', 'enabled'])
            ->where('user_id', $user->id)->whereIn('permission_id', $available->modelKeys())->get();
        $singleOrganization = $overrides->contains(fn (PermissionUserOverride $row): bool => $row->organization_id === null && $row->enabled)
            && $user->organizationMemberships()->count() === 1;
        $memberships = OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)->where('status', OrganizationUserStatus::Active->value)
            ->whereIn('organization_id', $this->activeOrganizationIds())
            ->when($singleCode !== null,
                fn ($query) => $query->withExists([
                    'role as role_allows_permission' => fn ($role) => $role->whereHas('permissions', fn ($permission) => $permission
                        ->where('permissions.code', $singleCode)->where('permission_role.enabled', true)),
                ]),
                fn ($query) => $query->with(['role' => fn ($role) => $role->select(['id'])->with([
                    'permissions' => fn ($permission) => $permission->whereIn('permissions.code', $codes),
                ])]),
            )->get();
        $organizationIds = $memberships->pluck('organization_id')->unique();
        $branches = $this->branchQuery($includeArchived)
            ->whereIn('organization_id', $organizationIds)->orderBy('id')->pluck('organization_id', 'id');
        $assignments = BranchUser::query()->select(['id', 'organization_id', 'branch_id', 'status'])
            ->where('user_id', $user->id)
            ->whereIn('organization_id', $organizationIds)->get();
        $result = [];
        foreach ($codes as $code) {
            if (! $available->has($code)) {
                $result[$code] = collect();

                continue;
            }
            $permissionId = $available[$code]->id;
            $allowedOrganizations = $memberships->filter(function (OrganizationUser $membership) use ($overrides, $singleOrganization, $singleCode, $code, $permissionId): bool {
                $effective = PermissionUserOverride::effectiveForOrganization($overrides, $membership->organization_id, $singleOrganization);

                return $effective->has($permissionId) ? (bool) $effective[$permissionId]
                    : ($singleCode !== null ? (bool) $membership->getAttribute('role_allows_permission')
                        : (bool) $membership->role?->permissions->contains(fn (Permission $permission): bool => $permission->code === $code && $this->permissionIsEnabled($permission)));
            })->pluck('organization_id')->unique();
            $allowedBranches = $branches->filter(fn (int $organizationId): bool => $allowedOrganizations->contains($organizationId));
            $result[$code] = $this->restrictToAssignments($allowedBranches, $assignments);
        }

        return $result;
    }

    /** @return Builder<Organization> */
    private function activeOrganizationIds(): Builder
    {
        return Organization::query()->select(['id'])->where(fn ($organization) => $organization
            ->whereDoesntHave('subscription')
            ->orWhereHas('subscription', fn ($subscription) => $subscription->where('status', OrganizationSubscriptionStatus::Active->value)));
    }

    /** @return Builder<Branch> */
    public function authorizedBranchQuery(User $user, bool $includeArchived = false): Builder
    {
        $query = $this->branchQuery($includeArchived)->select('branches.id');
        if ($user->isSuperadmin()) {
            return $query;
        }
        $assignments = BranchUser::query()->select('id')->where('user_id', $user->id)->whereColumn('organization_id', 'branches.organization_id');

        return $query->whereIn('organization_id', $this->activeOrganizationIds())
            ->whereIn('organization_id', OrganizationUser::query()->select('organization_id')->where('user_id', $user->id)->where('status', OrganizationUserStatus::Active->value))
            ->where(fn ($branch) => $branch->whereNotExists($assignments)
                ->orWhereExists((clone $assignments)->whereColumn('branch_id', 'branches.id')->where('status', OrganizationUserStatus::Active->value)));
    }

    /** @return Builder<Branch> */
    private function branchQuery(bool $includeArchived): Builder
    {
        return Branch::query()
            ->when($includeArchived, fn (Builder $query): Builder => $query->withTrashed())
            ->when(! $includeArchived, fn (Builder $query): Builder => $query->whereHas('brand', fn (Builder $brand): Builder => $brand
                ->whereNull('brands.deleted_at')->whereColumn('brands.organization_id', 'branches.organization_id')));
    }

    private function permissionIsEnabled(Permission $permission): bool
    {
        $pivot = $permission->getRelation('pivot');

        return $pivot instanceof Pivot && (bool) $pivot->getAttribute('enabled');
    }

    /**
     * @return Collection<int, int>
     */
    public function handle(User $user, SystemPermission $permissionCode = SystemPermission::ViewOrders, bool $includeArchived = false): Collection
    {
        return $this->handleMany($user, [$permissionCode], $includeArchived)[$permissionCode->value];
    }

    /** @param Collection<int,int> $branches @param Collection<int,BranchUser> $assignments @return Collection<int,int> */
    private function restrictToAssignments(Collection $branches, Collection $assignments): Collection
    {
        $restrictedOrganizations = $assignments->pluck('organization_id')->flip();
        $activeBranches = $assignments->filter(fn (BranchUser $assignment): bool => $assignment->status === OrganizationUserStatus::Active)->pluck('branch_id')->flip();

        return $branches->filter(fn (int $organizationId, int $branchId): bool => ! $restrictedOrganizations->has($organizationId) || $activeBranches->has($branchId))->keys()->values();
    }
}
