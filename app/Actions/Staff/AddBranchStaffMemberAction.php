<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\BranchAssignmentQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class AddBranchStaffMemberAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly BranchAssignmentQueryService $assignments,
    ) {}

    /** @param array{name?:string,email?:string,organization_membership_id?:int} $data */
    public function handle(Organization $organization, Branch $branch, Role $role, User $assignedBy, array $data, ?string $expectedAccessFingerprint = null, bool $confirmScopeRestriction = false): User
    {
        if ($branch->organization_id !== $organization->id) {
            throw new InvalidArgumentException('Branch must belong to the selected organization.');
        }

        return DB::transaction(function () use ($organization, $branch, $role, $assignedBy, $data, $expectedAccessFingerprint, $confirmScopeRestriction): User {
            $organization = Organization::query()->whereKey($organization->id)->firstOrFail();
            $branch = Branch::query()->whereKey($branch->id)->where('organization_id', $organization->id)->firstOrFail();
            $role = Role::query()->select(['id', 'code', 'name', 'sort_order'])->whereKey($role->id)->firstOrFail();
            $assignedBy = User::query()->with('roles')->whereKey($assignedBy->id)->firstOrFail();
            Gate::forUser($assignedBy)->authorize('manageStaff', $branch);
            Gate::forUser($assignedBy)->authorize('assign', [$role, $organization]);
            $email = mb_strtolower(trim($data['email'] ?? ''));
            $organizationMembership = OrganizationUser::query()
                ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
                ->with(['user', 'role:id,code,name,sort_order'])
                ->where('organization_id', $organization->id)
                ->where('status', OrganizationUserStatus::Active->value)
                ->when(isset($data['organization_membership_id']),
                    fn ($query) => $query->whereKey($data['organization_membership_id']),
                    fn ($query) => $query->whereHas('user', fn ($users) => $users->where('email', $email)))
                ->first();
            $user = $organizationMembership?->user;
            if (! $user instanceof User || ! $organizationMembership->role instanceof Role || $user->isSuperadmin()) {
                throw ValidationException::withMessages(['email' => __('staff.errors.invitation_required')]);
            }
            if ($user->id === $assignedBy->id) {
                throw new AuthorizationException;
            }
            Gate::forUser($assignedBy)->authorize('assign', [$organizationMembership->role, $organization]);
            $preview = $this->assignments->preview($organization, $branch, $organizationMembership, $assignedBy);
            if ($expectedAccessFingerprint !== null && ! hash_equals($preview['fingerprint'], $expectedAccessFingerprint)) {
                throw ValidationException::withMessages(['organizationMembershipId' => __('staff.errors.stale_membership')]);
            }
            $existing = BranchUser::query()->where('organization_id', $organization->id)
                ->where('branch_id', $branch->id)->where('user_id', $user->id)->first();
            if ($existing instanceof BranchUser) {
                if ($existing->status !== OrganizationUserStatus::Active) {
                    throw ValidationException::withMessages(['email' => __('staff.errors.membership_unavailable')]);
                }

                return $user;
            }
            if (! $preview['can_apply']) {
                throw new AuthorizationException;
            }
            if ($preview['requires_scope_confirmation'] && (! $confirmScopeRestriction || $expectedAccessFingerprint === null)) {
                throw ValidationException::withMessages(['organizationMembershipId' => __('staff.errors.scope_confirmation_required')]);
            }
            if (OrganizationUser::query()->whereKey($organizationMembership->id)->where('access_version', $organizationMembership->access_version)
                ->update(['access_version' => $organizationMembership->access_version + 1]) !== 1) {
                throw ValidationException::withMessages(['organizationMembershipId' => __('staff.errors.stale_membership')]);
            }
            $membership = new BranchUser;
            $membership->forceFill([
                'organization_id' => $organization->id, 'branch_id' => $branch->id,
                'user_id' => $user->id, 'role_id' => $role->id,
                'status' => OrganizationUserStatus::Active, 'assigned_at' => now(),
                'assigned_by_user_id' => $assignedBy->id,
            ]);
            if (! $membership->save()) {
                throw new RuntimeException('Branch membership could not be saved.');
            }

            $this->recordAuditLog->handle(
                action: AuditLogAction::StaffRoleChanged,
                entityType: 'branch_user', entityId: $membership->id, actorUser: $assignedBy,
                organizationId: $organization->id, branchId: $branch->id,
                oldValues: ['staff_user_id' => $user->id, 'role_id' => null, 'branch_access_mode' => $preview['mode'], 'branch_ids' => $preview['before_ids']],
                newValues: ['staff_user_id' => $user->id, 'role_id' => $role->id, 'role' => $role->code->value, 'assignment_created' => true, 'branch_access_mode' => 'assignments', 'branch_ids' => $preview['after_ids']],
            );

            return $user;
        });
    }
}
