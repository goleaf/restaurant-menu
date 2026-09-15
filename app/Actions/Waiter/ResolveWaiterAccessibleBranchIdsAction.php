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
    public function handleMany(User $user, array $permissions): array
    {
        $codes = array_map(fn (SystemPermission $permission): string => $permission->value, $permissions);
        if ($user->isSuperadmin()) {
            $branches = Branch::query()->select(['id'])->orderBy('id')->pluck('id');

            return array_fill_keys($codes, $branches);
        }

        $available = Permission::query()->select(['id', 'code'])->whereIn('code', $codes)->get()->keyBy('code');
        $overrides = $user->permissionOverrides()->whereIn('permissions.code', $codes)->get()->keyBy('code');
        $memberships = OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)->where('status', OrganizationUserStatus::Active->value)
            ->whereIn('organization_id', $this->activeOrganizationIds())
            ->with(['role' => fn ($query) => $query->select(['id'])->with([
                'permissions' => fn ($query) => $query->whereIn('permissions.code', $codes),
            ])])->get();
        $organizationIds = $memberships->pluck('organization_id')->unique();
        $branches = Branch::query()->whereIn('organization_id', $organizationIds)->orderBy('id')->pluck('organization_id', 'id');
        $assignments = BranchUser::query()->select(['id', 'organization_id', 'branch_id', 'status'])
            ->where('user_id', $user->id)
            ->whereIn('organization_id', $organizationIds)->get();
        $result = [];
        foreach ($codes as $code) {
            $override = $overrides->get($code);
            if (! $available->has($code) || ($override instanceof Permission && ! $this->permissionIsEnabled($override))) {
                $result[$code] = collect();

                continue;
            }
            $allowedOrganizations = $memberships->filter(fn (OrganizationUser $membership): bool => $override instanceof Permission
                || $membership->role?->permissions->contains(fn (Permission $permission): bool => $permission->code === $code && $this->permissionIsEnabled($permission)))
                ->pluck('organization_id')->unique();
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
    public function authorizedBranchQuery(User $user): Builder
    {
        $query = Branch::query()->select('branches.id');
        if ($user->isSuperadmin()) {
            return $query;
        }
        $assignments = BranchUser::query()->select('id')->where('user_id', $user->id)->whereColumn('organization_id', 'branches.organization_id');

        return $query->whereIn('organization_id', $this->activeOrganizationIds())
            ->whereIn('organization_id', OrganizationUser::query()->select('organization_id')->where('user_id', $user->id)->where('status', OrganizationUserStatus::Active->value))
            ->where(fn ($branch) => $branch->whereNotExists($assignments)
                ->orWhereExists((clone $assignments)->whereColumn('branch_id', 'branches.id')->where('status', OrganizationUserStatus::Active->value)));
    }

    private function permissionIsEnabled(Permission $permission): bool
    {
        $pivot = $permission->getRelation('pivot');

        return $pivot instanceof Pivot && (bool) $pivot->getAttribute('enabled');
    }

    /**
     * @return Collection<int, int>
     */
    public function handle(User $user, SystemPermission $permissionCode = SystemPermission::ViewOrders): Collection
    {
        if ($user->isSuperadmin()) {
            return Branch::query()
                ->select(['id'])
                ->orderBy('id')
                ->pluck('id');
        }

        $permission = Permission::query()
            ->select(['id', 'code'])
            ->where('code', $permissionCode->value)
            ->first();

        if (! $permission instanceof Permission) {
            return collect();
        }

        $override = $user->permissionOverrides()
            ->where('permissions.id', $permission->id)
            ->first();

        if ($override instanceof Permission && ! (bool) $override->pivot->enabled) {
            return collect();
        }

        $memberships = OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->whereIn('organization_id', $this->activeOrganizationIds())
            ->when(! $override instanceof Permission, function ($query) use ($permission): void {
                $query->whereHas('role.permissions', function ($permissionQuery) use ($permission): void {
                    $permissionQuery
                        ->where('permissions.id', $permission->id)
                        ->where('permission_role.enabled', true);
                });
            })
            ->orderBy('organization_id')
            ->get();

        if ($memberships->isEmpty()) {
            return collect();
        }

        $organizationIds = $memberships->pluck('organization_id')->unique()->values();
        $branches = Branch::query()
            ->select(['id', 'organization_id'])
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('id')
            ->pluck('organization_id', 'id');

        $assignments = BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'status'])
            ->where('user_id', $user->id)
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('branch_id')->get();

        return $this->restrictToAssignments($branches, $assignments);
    }

    /** @param Collection<int,int> $branches @param Collection<int,BranchUser> $assignments @return Collection<int,int> */
    private function restrictToAssignments(Collection $branches, Collection $assignments): Collection
    {
        $restrictedOrganizations = $assignments->pluck('organization_id')->flip();
        $activeBranches = $assignments->filter(fn (BranchUser $assignment): bool => $assignment->status === OrganizationUserStatus::Active)->pluck('branch_id')->flip();

        return $branches->filter(fn (int $organizationId, int $branchId): bool => ! $restrictedOrganizations->has($organizationId) || $activeBranches->has($branchId))->keys()->values();
    }
}
