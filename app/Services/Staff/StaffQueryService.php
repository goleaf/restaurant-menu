<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Paginator;

final class StaffQueryService
{
    /** @return Paginator<int, OrganizationUser> */
    public function paginateOrganizationMembers(Organization $organization, string $search, int $perPage): Paginator
    {
        $search = trim($search);

        return OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'joined_at', 'invited_by_user_id', 'created_at', 'updated_at'])
            ->with([
                'user' => fn ($query) => $query->select(['id', 'name', 'email']),
                'role' => fn ($query) => $query->select($this->roleColumns()),
            ])
            ->where('organization_id', $organization->id)
            ->when($search !== '', fn ($query) => $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderBy('status')
            ->orderByDesc('id')
            ->simplePaginate($perPage, pageName: 'organizationStaffPage');
    }

    /** @return Paginator<int, BranchUser> */
    public function paginateBranchMembers(Branch $branch, string $search, int $perPage): Paginator
    {
        $search = trim($search);

        return BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'assigned_at', 'assigned_by_user_id', 'created_at', 'updated_at'])
            ->with([
                'user' => fn ($query) => $query->select(['id', 'name', 'email']),
                'role' => fn ($query) => $query->select($this->roleColumns()),
            ])
            ->where('organization_id', $branch->organization_id)
            ->where('branch_id', $branch->id)
            ->when($search !== '', fn ($query) => $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderBy('status')
            ->orderByDesc('id')
            ->simplePaginate($perPage, pageName: 'branchStaffPage');
    }

    /** @return Paginator<int, Invitation> */
    public function paginateOrganizationInvitations(Organization $organization, string $search, int $perPage): Paginator
    {
        $search = trim($search);

        return Invitation::query()
            ->select($this->invitationColumns())
            ->with([
                'role' => fn ($query) => $query->select($this->roleColumns()),
                'invitedBy:id,name',
                'acceptedBy:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->whereNull('brand_id')
            ->whereNull('branch_id')
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('id')
            ->simplePaginate($perPage, pageName: 'organizationInvitationsPage');
    }

    /** @return Paginator<int, Invitation> */
    public function paginateBranchInvitations(
        Organization $organization,
        Branch $branch,
        string $search,
        int $perPage,
    ): Paginator {
        $search = trim($search);

        return Invitation::query()
            ->select($this->invitationColumns())
            ->with([
                'role' => fn ($query) => $query->select($this->roleColumns()),
                'invitedBy:id,name',
                'acceptedBy:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->where('brand_id', $branch->brand_id)
            ->where('branch_id', $branch->id)
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('id')
            ->simplePaginate($perPage, pageName: 'branchInvitationsPage');
    }

    /** @return EloquentCollection<int, Role> */
    public function assignableRoles(User $actor, Organization $organization): EloquentCollection
    {
        $roles = Role::query()
            ->select($this->roleColumns())
            ->where('code', '!=', SystemRole::Superadmin->value)
            ->orderBy('sort_order');

        if ($actor->isSuperadmin()) {
            return $roles->get();
        }

        $membership = OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status'])
            ->with(['role' => fn ($query) => $query->select($this->roleColumns())])
            ->where('organization_id', $organization->id)
            ->where('user_id', $actor->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->first();

        if (! $membership?->role instanceof Role) {
            return new EloquentCollection;
        }

        return $roles
            ->where('sort_order', '>', $membership->role->sort_order)
            ->get();
    }

    /** @return EloquentCollection<int, AreaNode> */
    public function activeAreaNodes(Branch $branch): EloquentCollection
    {
        return AreaNode::query()
            ->select(['id', 'branch_id', 'name', 'sort_order', 'is_active'])
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function defaultWaiterRoleId(): ?int
    {
        $roleId = Role::query()
            ->where('code', SystemRole::Waiter->value)
            ->value('id');

        return is_int($roleId) ? $roleId : null;
    }

    public function findAssignableRole(User $actor, Organization $organization, int $roleId): Role
    {
        $role = $this->assignableRoles($actor, $organization)->firstWhere('id', $roleId);

        if (! $role instanceof Role) {
            throw (new ModelNotFoundException)->setModel(Role::class, [$roleId]);
        }

        return $role;
    }

    public function findOrganizationMembership(Organization $organization, int $membershipId): OrganizationUser
    {
        return OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->whereKey($membershipId)
            ->firstOrFail();
    }

    public function findOrganizationInvitation(Organization $organization, int $invitationId): Invitation
    {
        return Invitation::query()
            ->select($this->invitationColumns())
            ->where('organization_id', $organization->id)
            ->whereNull('brand_id')
            ->whereNull('branch_id')
            ->whereKey($invitationId)
            ->firstOrFail();
    }

    public function findBranchUser(Branch $branch, int $branchUserId): BranchUser
    {
        return BranchUser::query()
            ->where('organization_id', $branch->organization_id)
            ->where('branch_id', $branch->id)
            ->whereKey($branchUserId)
            ->firstOrFail();
    }

    public function findBranchInvitation(Organization $organization, Branch $branch, int $invitationId): Invitation
    {
        return Invitation::query()
            ->select($this->invitationColumns())
            ->where('organization_id', $organization->id)
            ->where('brand_id', $branch->brand_id)
            ->where('branch_id', $branch->id)
            ->whereKey($invitationId)
            ->firstOrFail();
    }

    public function findBranchUserByUser(Branch $branch, int $userId): BranchUser
    {
        return BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'assigned_at', 'assigned_by_user_id', 'created_at', 'updated_at'])
            ->with(['role' => fn ($query) => $query->select($this->roleColumns())])
            ->where('organization_id', $branch->organization_id)
            ->where('branch_id', $branch->id)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    /** @return array<int, list<string>> */
    public function areaAssignments(Branch $branch): array
    {
        return AreaNodeWaiter::query()
            ->select(['id', 'branch_id', 'area_node_id', 'user_id'])
            ->where('organization_id', $branch->organization_id)
            ->where('branch_id', $branch->id)
            ->orderBy('user_id')
            ->orderBy('area_node_id')
            ->get()
            ->groupBy('user_id')
            ->map(fn (EloquentCollection $assignments): array => $assignments
                ->pluck('area_node_id')
                ->map(fn (int $areaNodeId): string => (string) $areaNodeId)
                ->values()
                ->all())
            ->all();
    }

    /** @return list<string> */
    private function roleColumns(): array
    {
        return ['id', 'code', 'name', 'sort_order'];
    }

    /** @return list<string> */
    private function invitationColumns(): array
    {
        return [
            'id',
            'organization_id',
            'brand_id',
            'branch_id',
            'role_id',
            'email',
            'phone',
            'expires_at',
            'status',
            'invited_by_user_id',
            'accepted_by_user_id',
            'accepted_at',
            'created_at',
            'updated_at',
        ];
    }
}
