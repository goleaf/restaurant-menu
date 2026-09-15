<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

final class StaffQueryService
{
    /** @return Paginator<int, OrganizationUser> */
    public function paginateOrganizationMembers(Organization $organization, string $search, int $perPage, array $filters = []): Paginator
    {
        $search = trim($search);

        return OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'joined_at', 'invited_by_user_id', 'created_at', 'updated_at', 'access_version'])
            ->with([
                'user' => fn ($query) => $query->select(['id', 'name', 'email'])->withExists(['roles as has_superadmin_role' => fn ($role) => $role->where('code', SystemRole::Superadmin->value)]),
                'role' => fn ($query) => $query->select($this->roleColumns()),
            ])
            ->whereBelongsTo($organization, 'organization')
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery
                ->whereAny(['name', 'email'], 'like', '%'.$search.'%')))
            ->when(($filters['role'] ?? '') !== '', fn ($query) => $query->whereHas('role', fn ($role) => $role->where('code', $filters['role'])))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['sort'] ?? '') === 'name', fn ($query) => $query->orderBy(User::query()->select('name')->whereColumn('users.id', $query->qualifyColumn('user_id'))->limit(1)))
            ->orderBy('id', ($filters['sort'] ?? '') === 'oldest' ? 'asc' : 'desc')
            ->simplePaginate($perPage, pageName: 'organizationStaffPage')->withQueryString();
    }

    /** @return Paginator<int, BranchUser> */
    public function paginateBranchMembers(Branch $branch, string $search, int $perPage, array $filters = []): Paginator
    {
        $search = trim($search);

        return BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'assigned_at', 'assigned_by_user_id', 'created_at', 'updated_at', 'access_version'])
            ->with([
                'user' => fn ($query) => $query->select(['id', 'name', 'email'])->withExists(['roles as has_superadmin_role' => fn ($role) => $role->where('code', SystemRole::Superadmin->value)])->withCount(['areaNodeAssignments as assigned_area_count' => fn ($areas) => $areas->where('organization_id', $branch->organization_id)->where('branch_id', $branch->id)]),
                'role' => fn ($query) => $query->select($this->roleColumns()),
            ])
            ->where('organization_id', $branch->organization_id)
            ->whereBelongsTo($branch, 'branch')
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery
                ->whereAny(['name', 'email'], 'like', '%'.$search.'%')))
            ->when(($filters['role'] ?? '') !== '', fn ($query) => $query->whereHas('role', fn ($role) => $role->where('code', $filters['role'])))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['sort'] ?? '') === 'name', fn ($query) => $query->orderBy(User::query()->select('name')->whereColumn('users.id', $query->qualifyColumn('user_id'))->limit(1)))
            ->orderBy('id', ($filters['sort'] ?? '') === 'oldest' ? 'asc' : 'desc')
            ->simplePaginate($perPage, pageName: 'branchStaffPage')->withQueryString();
    }

    /** @return Paginator<int, Invitation> */
    public function paginateOrganizationInvitations(Organization $organization, string $search, int $perPage, array $filters = []): Paginator
    {
        $search = trim($search);

        return Invitation::query()
            ->select($this->invitationColumns())
            ->with([
                'role' => fn ($query) => $query->select($this->roleColumns()),
                'invitedBy:id,name',
                'acceptedBy:id,name',
            ])
            ->whereBelongsTo($organization, 'organization')
            ->whereNull('brand_id')
            ->whereNull('branch_id')
            ->when($search !== '', fn ($query) => $query->whereAny(['email', 'phone'], 'like', '%'.$search.'%'))
            ->when(($filters['role'] ?? '') !== '', fn ($query) => $query->whereHas('role', fn ($role) => $role->where('code', $filters['role'])))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->withEffectiveStatus(InvitationStatus::from($filters['status'])))
            ->when(($filters['sort'] ?? '') === 'name', fn ($query) => $query->orderBy('email'))
            ->orderBy('id', ($filters['sort'] ?? '') === 'oldest' ? 'asc' : 'desc')
            ->simplePaginate($perPage, pageName: 'organizationInvitationsPage')->withQueryString();
    }

    /** @return Paginator<int, Invitation> */
    public function paginateBranchInvitations(
        Organization $organization,
        Branch $branch,
        string $search,
        int $perPage,
        array $filters = [],
    ): Paginator {
        $search = trim($search);

        return Invitation::query()
            ->select($this->invitationColumns())
            ->with([
                'role' => fn ($query) => $query->select($this->roleColumns()),
                'invitedBy:id,name',
                'acceptedBy:id,name',
            ])
            ->whereBelongsTo($organization, 'organization')
            ->where('brand_id', $branch->brand_id)
            ->whereBelongsTo($branch, 'branch')
            ->when($search !== '', fn ($query) => $query->whereAny(['email', 'phone'], 'like', '%'.$search.'%'))
            ->when(($filters['role'] ?? '') !== '', fn ($query) => $query->whereHas('role', fn ($role) => $role->where('code', $filters['role'])))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->withEffectiveStatus(InvitationStatus::from($filters['status'])))
            ->when(($filters['sort'] ?? '') === 'name', fn ($query) => $query->orderBy('email'))
            ->orderBy('id', ($filters['sort'] ?? '') === 'oldest' ? 'asc' : 'desc')
            ->simplePaginate($perPage, pageName: 'branchInvitationsPage')->withQueryString();
    }

    /** @param array{search:string,role:string,status:string,sort:string} $filters @return list<array{status:string,label:string,count:int}> */
    public function invitationSummary(Organization $organization, ?Branch $branch, array $filters): array
    {
        $counts = [];
        foreach (InvitationStatus::cases() as $status) {
            $counts['invitations as '.$status->value.'_count'] = fn ($query) => $query
                ->when($branch instanceof Branch,
                    fn ($query) => $query->where('brand_id', $branch->brand_id)->where('branch_id', $branch->id),
                    fn ($query) => $query->whereNull('brand_id')->whereNull('branch_id'))
                ->when($filters['search'] !== '', fn ($query) => $query->whereAny(['email', 'phone'], 'like', '%'.$filters['search'].'%'))
                ->when($filters['role'] !== '', fn ($query) => $query->whereHas('role', fn ($role) => $role->where('code', $filters['role'])))
                ->withEffectiveStatus($status);
        }
        $summary = Organization::query()->select('id')->whereKey($organization->id)->withCount($counts)->firstOrFail();

        return array_map(fn (InvitationStatus $status): array => [
            'status' => $status->value, 'label' => $status->localizedLabel(), 'count' => (int) $summary->getAttribute($status->value.'_count'),
        ], InvitationStatus::cases());
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
            ->whereBelongsTo($organization, 'organization')
            ->whereBelongsTo($actor, 'user')
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
            ->select(['id', 'branch_id', 'parent_id', 'name', 'sort_order', 'is_active'])
            ->whereBelongsTo($branch, 'branch')
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

    /** @return list<string> */
    public function roleAccessPreview(Role $role): array
    {
        return $role->permissions()->select(['permissions.id', 'permissions.code', 'permissions.sort_order'])
            ->wherePivot('enabled', true)->whereIn('permissions.code', array_column(SystemPermission::cases(), 'value'))
            ->orderBy('permissions.sort_order')->orderBy('permissions.id')->limit(count(SystemPermission::cases()))->get()
            ->map(fn ($permission): string => __(SystemPermission::from($permission->code)->uiLabelKey()))->all();
    }

    public function findOrganizationMembership(Organization $organization, int $membershipId): OrganizationUser
    {
        return OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->with(['user:id,name,email', 'role:id,code,name,sort_order'])
            ->whereBelongsTo($organization, 'organization')
            ->whereKey($membershipId)
            ->firstOrFail();
    }

    public function findOrganizationInvitation(Organization $organization, int $invitationId): Invitation
    {
        return Invitation::query()
            ->select([...$this->invitationColumns(), 'invite_token_hash', 'invite_code_hash'])
            ->with(['role:id,code,name,sort_order'])
            ->whereBelongsTo($organization, 'organization')
            ->whereNull('brand_id')
            ->whereNull('branch_id')
            ->whereKey($invitationId)
            ->firstOrFail();
    }

    public function findBranchUser(Branch $branch, int $branchUserId): BranchUser
    {
        return BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->with(['user:id,name,email', 'role:id,code,name,sort_order'])
            ->where('organization_id', $branch->organization_id)
            ->whereBelongsTo($branch, 'branch')
            ->whereKey($branchUserId)
            ->firstOrFail();
    }

    public function findBranchInvitation(Organization $organization, Branch $branch, int $invitationId): Invitation
    {
        return Invitation::query()
            ->select([...$this->invitationColumns(), 'invite_token_hash', 'invite_code_hash'])
            ->with(['role:id,code,name,sort_order'])
            ->whereBelongsTo($organization, 'organization')
            ->where('brand_id', $branch->brand_id)
            ->whereBelongsTo($branch, 'branch')
            ->whereKey($invitationId)
            ->firstOrFail();
    }

    public function findBranchUserByUser(Branch $branch, int $userId): BranchUser
    {
        return BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'assigned_at', 'assigned_by_user_id', 'created_at', 'updated_at', 'access_version'])
            ->with(['role' => fn ($query) => $query->select($this->roleColumns())])
            ->where('organization_id', $branch->organization_id)
            ->whereBelongsTo($branch, 'branch')
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    /** @return array{ids:list<int>,fingerprint:string} */
    public function assignmentSnapshot(Branch $branch, BranchUser $membership): array
    {
        $ids = AreaNodeWaiter::query()->select('area_node_id')
            ->where('organization_id', $branch->organization_id)->where('branch_id', $branch->id)
            ->where('user_id', $membership->user_id)->orderBy('area_node_id')->pluck('area_node_id')->all();

        return ['ids' => $ids, 'fingerprint' => hash('sha256', json_encode([$branch->id, $membership->id, $membership->role_id, $membership->status->value, $membership->access_version, $ids], JSON_THROW_ON_ERROR))];
    }

    /** @return array{organization:Organization,brand:Brand|null,branch:Branch|null} */
    public function context(int $organizationId, ?int $brandId, ?int $branchId): array
    {
        $organization = Organization::query()->select(['id', 'name', 'is_active'])->whereKey($organizationId)->firstOrFail();
        $brand = $branch = null;
        if ($branchId !== null) {
            $brand = Brand::query()->select(['id', 'organization_id', 'name', 'is_active'])->where('organization_id', $organizationId)->whereKey($brandId)->firstOrFail();
            $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'timezone', 'is_active'])->where('organization_id', $organizationId)->where('brand_id', $brand->id)->whereKey($branchId)->firstOrFail();
        }

        return compact('organization', 'brand', 'branch');
    }

    /** @param list<int> $assignableRoleIds @return array<string,mixed> */
    public function memberRow(OrganizationUser|BranchUser $member, User $actor, array $assignableRoleIds): array
    {
        return ['id' => $member->id, 'user_id' => $member->user_id, 'user_name' => $member->user->name, 'user_email' => $member->user->email,
            'role_id' => $member->role_id, 'role_label' => $member->role?->code->localizedLabel(), 'status' => $member->status->value,
            'localized_status' => $member->status->localizedLabel(), 'is_active' => $member->status === OrganizationUserStatus::Active,
            'is_waiter' => $member->role?->code === SystemRole::Waiter, 'can_manage' => ! ($member->user->getAttributes()['has_superadmin_role'] ?? false) && $member->user_id !== $actor->id && in_array($member->role_id, $assignableRoleIds, true),
            'access_version' => $member->access_version, 'coverage' => $member instanceof BranchUser && $member->role?->code === SystemRole::Waiter && array_key_exists('assigned_area_count', $member->user->getAttributes())
                ? ((($member->user->getAttributes()['assigned_area_count'] ?? null) ?? 0) === 0 ? __('staff.workspace.coverage_all') : __('staff.workspace.coverage_count', ['count' => ($member->user->getAttributes()['assigned_area_count'] ?? null)])) : null];
    }

    /** @return array<string,mixed> */
    public function invitationRow(Invitation $invitation, array $assignableRoleIds): array
    {
        $status = $invitation->effectiveStatus();

        return ['id' => $invitation->id, 'email' => $invitation->email, 'phone' => $invitation->phone,
            'role_label' => $invitation->role?->code->localizedLabel(), 'localized_status' => $status->localizedLabel(),
            'created_by' => $invitation->invitedBy?->name, 'accepted_by' => $invitation->acceptedBy?->name,
            'created_at' => LocalizedDateFormatter::dateTime($invitation->created_at), 'expires_at' => LocalizedDateFormatter::dateTime($invitation->expires_at),
            'accepted_at' => LocalizedDateFormatter::dateTime($invitation->accepted_at), 'can_cancel' => $status === InvitationStatus::Pending && in_array($invitation->role_id, $assignableRoleIds, true),
            'can_reissue' => in_array($invitation->role_id, $assignableRoleIds, true) && in_array($status, [InvitationStatus::Pending, InvitationStatus::Expired], true)];
    }

    /** @param list<int> $userIds @param list<int> $assignableRoleIds @return array<int,string> */
    public function permissionLinks(Organization $organization, User $actor, array $userIds, array $assignableRoleIds): array
    {
        if ($userIds === [] || ! Gate::forUser($actor)->allows('managePermissions', $organization)) {
            return [];
        }
        $members = OrganizationUser::query()->select(['user_id'])->where('organization_id', $organization->id)
            ->where('status', OrganizationUserStatus::Active->value)->whereIn('user_id', $userIds)->where('user_id', '!=', $actor->id)
            ->whereIn('role_id', $assignableRoleIds)
            ->whereDoesntHave('user.roles', fn ($query) => $query->where('code', SystemRole::Superadmin->value))->pluck('user_id');

        return $members->mapWithKeys(fn (int $userId): array => [$userId => route('organizations.staff.permissions', ['organization' => $organization->id, 'staffMember' => $userId])])->all();
    }

    /** @return array<string,mixed> */
    public function coverageOverview(Branch $branch): array
    {
        $organizationUsers = OrganizationUser::query()->select('user_id')->where('organization_id', $branch->organization_id)
            ->where('status', OrganizationUserStatus::Active->value);
        $branchUsers = BranchUser::query()->select('user_id')->where('organization_id', $branch->organization_id)->where('branch_id', $branch->id)
            ->where('status', OrganizationUserStatus::Active->value)->whereIn('user_id', $organizationUsers)
            ->whereHas('role', fn ($query) => $query->where('code', SystemRole::Waiter->value));
        $waiters = User::query()->select(['id', 'name'])->whereIn('id', $branchUsers)
            ->whereDoesntHave('roles', fn ($query) => $query->where('code', SystemRole::Superadmin->value));
        $waiterIds = (clone $waiters)->select('id');
        $unrestricted = (clone $waiters)->whereDoesntHave('areaNodeAssignments', fn ($query) => $query->where('branch_id', $branch->id));
        $unrestrictedCount = (clone $unrestricted)->count();
        $unrestrictedNames = $unrestricted->orderBy('name')->orderBy('id')->limit(3)->pluck('name')->all();
        $matching = fn ($query) => $query->where('branch_id', $branch->id)->whereIn('user_id', $waiterIds);
        $areas = AreaNode::query()->select(['id', 'branch_id', 'parent_id', 'name'])->where('branch_id', $branch->id)->where('is_active', true)
            ->withCount(['waiterAssignments as explicit_count' => $matching])
            ->with(['waiterAssignments' => fn ($query) => $matching($query)->select(['id', 'branch_id', 'area_node_id', 'user_id'])
                ->with('user:id,name')->orderBy('id')->limit(3)])
            ->orderBy('name')->orderBy('id')->simplePaginate(20, pageName: 'coveragePage');

        return ['unrestricted_count' => $unrestrictedCount, 'unrestricted_names' => $unrestrictedNames, 'paginator' => $areas,
            'rows' => $areas->getCollection()->map(fn (AreaNode $area): array => ['id' => $area->id, 'name' => $area->name,
                'assigned_count' => (int) $area->getAttribute('explicit_count') + $unrestrictedCount,
                'waiter_names' => $area->waiterAssignments->map(fn (AreaNodeWaiter $assignment): string => $assignment->user->name)->all(),
            ])->all()];
    }

    /** @return EloquentCollection<int,OrganizationUser> */
    public function assignableOrganizationMembers(Organization $organization, Branch $branch, string $search, User $actor): EloquentCollection
    {
        return OrganizationUser::query()->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->with(['user:id,name,email'])->where('organization_id', $organization->id)->where('status', OrganizationUserStatus::Active->value)
            ->where('user_id', '!=', $actor->id)->whereIn('role_id', $this->assignableRoles($actor, $organization)->modelKeys())
            ->whereDoesntHave('user.roles', fn ($query) => $query->where('code', SystemRole::Superadmin->value))
            ->whereNotIn('user_id', BranchUser::query()->select('user_id')->where('branch_id', $branch->id))
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->whereAny(['name', 'email'], 'like', '%'.trim($search).'%')))
            ->orderByDesc('id')->limit(25)->get();
    }

    /** @param list<int> $selected @param list<int> $current @return array<string,mixed> */
    public function areaEditor(Branch $branch, array $selected, string $search, array $current = []): array
    {
        $areas = AreaNode::query()->select(['id', 'branch_id', 'parent_id', 'name', 'sort_order', 'is_active'])
            ->with(['parent' => fn ($query) => $query->select(['id', 'branch_id', 'name'])->where('branch_id', $branch->id)])
            ->where('branch_id', $branch->id)->where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.trim($search).'%'))
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->simplePaginate(40, pageName: 'assignmentAreasPage');
        $groups = [];
        foreach ($areas as $area) {
            $label = $area->parent->name ?? __('staff.workspace.area_group');
            $groups[$area->parent_id ?? 0] ??= ['label' => $label, 'areas' => []];
            $groups[$area->parent_id ?? 0]['areas'][] = ['id' => $area->id, 'name' => $area->name];
        }
        $reviewIds = array_values(array_unique([...$current, ...$selected]));
        $reviewAreas = $reviewIds === [] ? new EloquentCollection : AreaNode::query()->withTrashed()
            ->select(['id', 'branch_id', 'parent_id', 'name', 'is_active', 'deleted_at'])
            ->with(['parent' => fn ($query) => $query->select(['id', 'branch_id', 'name'])->where('branch_id', $branch->id)])
            ->where('branch_id', $branch->id)->whereIn('id', $reviewIds)->get()->keyBy('id');
        $rows = static fn (array $ids): array => array_map(static function (int $id) use ($reviewAreas): array {
            $area = $reviewAreas->get($id);

            return ['id' => $id, 'label' => $area instanceof AreaNode
                ? ($area->parent?->name !== null ? $area->parent->name.' → ' : '').$area->name
                : __('staff.workspace.unavailable_area'),
                'available' => $area instanceof AreaNode && $area->is_active && ! $area->trashed()];
        }, array_values($ids));
        $selectedRows = $rows($selected);

        return ['groups' => array_values($groups), 'paginator' => $areas,
            'unavailable' => array_values(array_filter($selectedRows, static fn (array $row): bool => ! $row['available'])),
            'selected' => $selectedRows, 'current' => $rows($current),
            'added' => $rows(array_diff($selected, $current)), 'removed' => $rows(array_diff($current, $selected))];
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
