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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class AddBranchStaffMemberAction
{
    public function __construct(private readonly RecordAuditLogAction $recordAuditLog) {}

    /** @param array{name?: string, email: string} $data */
    public function handle(Organization $organization, Branch $branch, Role $role, User $assignedBy, array $data): User
    {
        if ($branch->organization_id !== $organization->id) {
            throw new InvalidArgumentException('Branch must belong to the selected organization.');
        }

        return DB::transaction(function () use ($organization, $branch, $role, $assignedBy, $data): User {
            $organization = Organization::query()->whereKey($organization->id)->firstOrFail();
            $branch = Branch::query()->whereKey($branch->id)->where('organization_id', $organization->id)->firstOrFail();
            $role = Role::query()->select(['id', 'code', 'name', 'sort_order'])->whereKey($role->id)->firstOrFail();
            $assignedBy = User::query()->with('roles')->whereKey($assignedBy->id)->firstOrFail();
            Gate::forUser($assignedBy)->authorize('manageStaff', $branch);
            Gate::forUser($assignedBy)->authorize('assign', [$role, $organization]);
            $email = mb_strtolower(trim($data['email']));
            $organizationMembership = OrganizationUser::query()
                ->select(['id', 'organization_id', 'user_id', 'role_id', 'status'])
                ->with(['user', 'role:id,code,name,sort_order'])
                ->where('organization_id', $organization->id)
                ->where('status', OrganizationUserStatus::Active->value)
                ->whereHas('user', fn ($query) => $query->where('email', $email))->first();
            $user = $organizationMembership?->user;
            if (! $user instanceof User || ! $organizationMembership->role instanceof Role || $user->isSuperadmin()) {
                throw ValidationException::withMessages(['email' => __('staff.errors.invitation_required')]);
            }
            if ($user->id === $assignedBy->id) {
                throw new AuthorizationException;
            }
            Gate::forUser($assignedBy)->authorize('assign', [$organizationMembership->role, $organization]);
            $existing = BranchUser::query()->where('organization_id', $organization->id)
                ->where('branch_id', $branch->id)->where('user_id', $user->id)->first();
            if ($existing instanceof BranchUser) {
                if ($existing->status !== OrganizationUserStatus::Active) {
                    throw ValidationException::withMessages(['email' => __('staff.errors.membership_unavailable')]);
                }

                return $user;
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
                oldValues: ['staff_user_id' => $user->id, 'role_id' => null],
                newValues: ['staff_user_id' => $user->id, 'role_id' => $role->id, 'role' => $role->code->value, 'assignment_created' => true],
            );

            return $user;
        });
    }
}
