<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateOrganizationStaffRoleAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(
        User $actor,
        Organization $organization,
        OrganizationUser $membership,
        Role $role,
        string $reason,
    ): OrganizationUser {
        Gate::forUser($actor)->authorize('manageStaff', $organization);

        $reason = $this->validatedReason($reason);

        return DB::transaction(function () use ($actor, $organization, $membership, $role, $reason): OrganizationUser {
            $scopedMembership = OrganizationUser::query()
                ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'joined_at', 'invited_by_user_id', 'created_at', 'updated_at'])
                ->where('organization_id', $organization->id)
                ->whereKey($membership->id)
                ->lockForUpdate()
                ->firstOrFail();
            $assignableRole = $this->findAssignableRole($role);
            Gate::forUser($actor)->authorize('assign', [$assignableRole, $organization]);

            if ((int) $scopedMembership->user_id === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'editingRoleId' => __('staff.errors.self_role_change_blocked'),
                ]);
            }

            $currentRole = Role::query()
                ->select(['id', 'code', 'name', 'sort_order'])
                ->whereKey($scopedMembership->role_id)
                ->firstOrFail();

            $this->ensureActiveOwnerRemains($organization, $scopedMembership, $currentRole, $assignableRole);

            if ((int) $scopedMembership->role_id === (int) $assignableRole->id) {
                return $scopedMembership;
            }

            $previousRoleId = (int) $scopedMembership->role_id;
            $scopedMembership->forceFill(['role_id' => $assignableRole->id])->saveOrFail();

            $this->recordAuditLog->handle(
                action: AuditLogAction::StaffRoleChanged,
                entityType: 'organization_user',
                entityId: $scopedMembership->id,
                actorUser: $actor,
                organizationId: $organization->id,
                oldValues: [
                    'staff_user_id' => $scopedMembership->user_id,
                    'role_id' => $previousRoleId,
                    'role' => $currentRole->code->value,
                ],
                newValues: [
                    'staff_user_id' => $scopedMembership->user_id,
                    'role_id' => $assignableRole->id,
                    'role' => $assignableRole->code->value,
                    'reason' => $reason,
                ],
            );

            return $scopedMembership->refresh();
        });
    }

    private function findAssignableRole(Role $role): Role
    {
        $assignableRole = Role::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($role->id)
            ->where('code', '!=', SystemRole::Superadmin->value)
            ->first();

        if (! $assignableRole instanceof Role) {
            throw ValidationException::withMessages([
                'editingRoleId' => __('staff.errors.role_unavailable'),
            ]);
        }

        return $assignableRole;
    }

    private function ensureActiveOwnerRemains(
        Organization $organization,
        OrganizationUser $membership,
        Role $currentRole,
        Role $newRole,
    ): void {
        if ($currentRole->code !== SystemRole::Owner || $newRole->code === SystemRole::Owner) {
            return;
        }

        $activeOwnerCount = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->whereHas('role', fn ($query) => $query->where('code', SystemRole::Owner->value))
            ->count();

        if ($activeOwnerCount <= 1 && $membership->status === OrganizationUserStatus::Active) {
            throw ValidationException::withMessages([
                'editingRoleId' => __('staff.errors.last_owner_role_change_blocked'),
            ]);
        }
    }

    private function validatedReason(string $reason): string
    {
        $reason = trim($reason);

        return (string) Validator::make(
            ['reason' => $reason],
            RestaurantValidationRules::auditReason('reason'),
        )->validate()['reason'];
    }
}
