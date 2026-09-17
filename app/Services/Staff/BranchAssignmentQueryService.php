<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\OrganizationUserStatus;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class BranchAssignmentQueryService
{
    /**
     * Restaurant identities and names are returned only inside the actor's accessible scope.
     *
     * @return array{fingerprint:string,mode:string,before_ids:list<int>,after_ids:list<int>,lost_ids:list<int>,gained_ids:list<int>,before:list<array{id:int,name:string}>,after:list<array{id:int,name:string}>,lost:list<array{id:int,name:string}>,gained:list<array{id:int,name:string}>,outside_scope_count:int,requires_scope_confirmation:bool,can_apply:bool}
     */
    public function preview(Organization $organization, Branch $branch, OrganizationUser $membership, User $actor): array
    {
        $actor = User::query()->with('roles')->whereKey($actor->id)->firstOrFail();
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'deleted_at'])
            ->where('organization_id', $organization->id)->whereKey($branch->id)->firstOrFail();
        Gate::forUser($actor)->authorize('manageStaff', $branch);
        $current = OrganizationUser::query()->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->with(['user', 'role:id,code,name,sort_order'])
            ->where('organization_id', $organization->id)->where('user_id', $membership->user_id)->whereKey($membership->id)->firstOrFail();
        Gate::forUser($actor)->authorize('assign', [$current->role, $organization]);
        if ($current->user_id === $actor->id || $current->user->isSuperadmin()) {
            throw new AuthorizationException;
        }
        if ($current->status !== OrganizationUserStatus::Active) {
            throw ValidationException::withMessages(['organizationMembershipId' => __('staff.errors.membership_unavailable')]);
        }

        $assignments = BranchUser::query()->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->where('organization_id', $organization->id)->where('user_id', $current->user_id)->orderBy('id')->get();
        $branches = Branch::query()->select(['id', 'name'])->where('organization_id', $organization->id)->orderBy('id')->get();
        $inherited = $assignments->isEmpty();
        $beforeIds = $inherited ? $branches->modelKeys() : $assignments->where('status', OrganizationUserStatus::Active)->pluck('branch_id')->all();
        $existing = $assignments->firstWhere('branch_id', $branch->id);
        $afterIds = $inherited ? [$branch->id] : array_values(array_unique([...$beforeIds, ...($existing === null ? [$branch->id] : [])]));
        sort($beforeIds);
        sort($afterIds);
        $lostIds = array_values(array_diff($beforeIds, $afterIds));
        $gainedIds = array_values(array_diff($afterIds, $beforeIds));
        $actorBranchIds = $actor->accessibleBranchIdsForOrganization($organization)->all();
        $outsideScopeCount = count(array_diff($inherited ? $beforeIds : [$branch->id], $actorBranchIds));
        $canApply = $outsideScopeCount === 0 && (! $inherited || Gate::forUser($actor)->allows('manageStaff', $organization))
            && ($existing === null || $existing->status === OrganizationUserStatus::Active);
        $visible = static fn (array $ids): array => array_values(array_intersect($ids, $actorBranchIds));
        $rows = static fn (array $ids): array => $branches->whereIn('id', $visible($ids))
            ->map(static fn (Branch $restaurant): array => ['id' => $restaurant->id, 'name' => $restaurant->name])->values()->all();

        return [
            'fingerprint' => hash('sha256', json_encode([
                $organization->id, $branch->id, $branch->brand_id,
                $current->only(['id', 'user_id', 'role_id', 'status', 'access_version']),
                $assignments->map(static fn (BranchUser $assignment): array => $assignment->only(['id', 'branch_id', 'role_id', 'status', 'access_version']))->all(),
                $branches->modelKeys(),
            ], JSON_THROW_ON_ERROR)),
            'mode' => $inherited ? 'organization' : 'assignments',
            'before_ids' => $visible($beforeIds), 'after_ids' => $visible($afterIds),
            'lost_ids' => $visible($lostIds), 'gained_ids' => $visible($gainedIds),
            'before' => $rows($beforeIds), 'after' => $rows($afterIds), 'lost' => $rows($lostIds), 'gained' => $rows($gainedIds),
            'outside_scope_count' => $outsideScopeCount,
            'requires_scope_confirmation' => $inherited,
            'can_apply' => $canApply,
        ];
    }

    /**
     * Deleting the final explicit assignment is a distinct return to inherited scope.
     * Names and identifiers outside the actor's visible restaurants remain private.
     *
     * @return array{fingerprint:string,operation:string,branch_assignment_id:int,access_version:int,area_count:int,mode:string,before_ids:list<int>,after_ids:list<int>,lost_ids:list<int>,gained_ids:list<int>,before:list<array{id:int,name:string}>,after:list<array{id:int,name:string}>,lost:list<array{id:int,name:string}>,gained:list<array{id:int,name:string}>,outside_scope_count:int,requires_scope_confirmation:bool,can_apply:bool}
     */
    public function removalPreview(Organization $organization, Branch $branch, OrganizationUser $membership, User $actor): array
    {
        $actor = User::query()->with('roles')->whereKey($actor->id)->firstOrFail();
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'deleted_at'])
            ->where('organization_id', $organization->id)->where('brand_id', $branch->brand_id)->whereKey($branch->id)->firstOrFail();
        Gate::forUser($actor)->authorize('manageStaff', $branch);
        $current = OrganizationUser::query()->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->with(['user', 'role:id,code,name,sort_order'])
            ->where('organization_id', $organization->id)->where('user_id', $membership->user_id)->whereKey($membership->id)->firstOrFail();
        if ($current->user_id === $actor->id || $current->user->isSuperadmin()) {
            throw new AuthorizationException;
        }
        Gate::forUser($actor)->authorize('assign', [$current->role, $organization]);
        if ($current->status !== OrganizationUserStatus::Active) {
            throw ValidationException::withMessages(['organizationMembershipId' => __('staff.errors.membership_unavailable')]);
        }
        $assignments = BranchUser::query()->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->with('role:id,code,name,sort_order')
            ->where('organization_id', $organization->id)->where('user_id', $current->user_id)->orderBy('id')->get();
        $assignment = $assignments->firstWhere('branch_id', $branch->id);
        if (! $assignment instanceof BranchUser) {
            throw ValidationException::withMessages(['organizationMembershipId' => __('staff.errors.stale_membership')]);
        }
        Gate::forUser($actor)->authorize('assign', [$assignment->role, $organization]);
        $branches = Branch::query()->select(['id', 'name'])->where('organization_id', $organization->id)->orderBy('id')->get();
        $areas = AreaNodeWaiter::query()->select(['id', 'area_node_id'])
            ->where('organization_id', $organization->id)->where('branch_id', $branch->id)->where('user_id', $current->user_id)->orderBy('id')->get();
        $last = $assignments->count() === 1;
        $operation = $last ? 'return_to_organization' : 'remove_assignment';
        $beforeIds = $assignments->where('status', OrganizationUserStatus::Active)->pluck('branch_id')->all();
        $afterIds = $last ? $branches->modelKeys() : array_values(array_diff($beforeIds, [$branch->id]));
        sort($beforeIds);
        sort($afterIds);
        $lostIds = array_values(array_diff($beforeIds, $afterIds));
        $gainedIds = array_values(array_diff($afterIds, $beforeIds));
        $actorBranchIds = array_values(array_intersect($actor->accessibleBranchIdsForOrganization($organization)->all(), $branches->modelKeys()));
        $outsideScopeCount = count(array_diff($last ? $afterIds : [$branch->id], $actorBranchIds));
        $canApply = $outsideScopeCount === 0 && (! $last || Gate::forUser($actor)->allows('manageStaff', $organization));
        $visible = static fn (array $ids): array => array_values(array_intersect($ids, $actorBranchIds));
        $rows = static fn (array $ids): array => $branches->whereIn('id', $visible($ids))
            ->map(static fn (Branch $restaurant): array => ['id' => $restaurant->id, 'name' => $restaurant->name])->values()->all();

        return [
            'fingerprint' => hash('sha256', json_encode([
                $operation, $organization->id, $branch->id, $branch->brand_id,
                $current->only(['id', 'user_id', 'role_id', 'status', 'access_version']),
                $assignments->map(static fn (BranchUser $row): array => $row->only(['id', 'branch_id', 'role_id', 'status', 'access_version']))->all(),
                $branches->modelKeys(), $areas->map(static fn (AreaNodeWaiter $row): array => $row->only(['id', 'area_node_id']))->all(),
            ], JSON_THROW_ON_ERROR)),
            'operation' => $operation, 'branch_assignment_id' => $assignment->id, 'access_version' => $assignment->access_version,
            'area_count' => $areas->count(), 'mode' => 'assignments',
            'before_ids' => $visible($beforeIds), 'after_ids' => $visible($afterIds),
            'lost_ids' => $visible($lostIds), 'gained_ids' => $visible($gainedIds),
            'before' => $rows($beforeIds), 'after' => $rows($afterIds), 'lost' => $rows($lostIds), 'gained' => $rows($gainedIds),
            'outside_scope_count' => $outsideScopeCount, 'requires_scope_confirmation' => $last, 'can_apply' => $canApply,
        ];
    }
}
