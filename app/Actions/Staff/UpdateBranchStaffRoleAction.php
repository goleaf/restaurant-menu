<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\SystemRole;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateBranchStaffRoleAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(User $actor, Branch $branch, BranchUser $branchUser, Role $role, string $reason): BranchUser
    {
        Gate::forUser($actor)->authorize('manageStaff', $branch);

        $reason = $this->validatedReason($reason);

        return DB::transaction(function () use ($actor, $branch, $branchUser, $role, $reason): BranchUser {
            $organization = Organization::query()
                ->select(['id', 'owner_user_id', 'name', 'slug', 'default_locale', 'timezone', 'currency_code', 'status', 'created_at', 'updated_at', 'deleted_at'])
                ->whereKey($branch->organization_id)
                ->firstOrFail();
            $scopedBranchUser = BranchUser::query()
                ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'assigned_at', 'assigned_by_user_id', 'created_at', 'updated_at'])
                ->where('organization_id', $branch->organization_id)
                ->where('branch_id', $branch->id)
                ->whereKey($branchUser->id)
                ->lockForUpdate()
                ->firstOrFail();
            $assignableRole = $this->findAssignableRole($role);
            Gate::forUser($actor)->authorize('assign', [$assignableRole, $organization]);

            if ((int) $scopedBranchUser->user_id === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'editingRoleId' => __('staff.errors.self_role_change_blocked'),
                ]);
            }

            if ((int) $scopedBranchUser->role_id === (int) $assignableRole->id) {
                return $scopedBranchUser;
            }

            $currentRole = Role::query()
                ->select(['id', 'code', 'name', 'sort_order'])
                ->whereKey($scopedBranchUser->role_id)
                ->firstOrFail();
            $previousRoleId = (int) $scopedBranchUser->role_id;

            $scopedBranchUser->forceFill(['role_id' => $assignableRole->id])->saveOrFail();

            if ($assignableRole->code !== SystemRole::Waiter) {
                AreaNodeWaiter::query()
                    ->where('organization_id', $branch->organization_id)
                    ->where('branch_id', $branch->id)
                    ->where('user_id', $scopedBranchUser->user_id)
                    ->delete();
            }

            $this->recordAuditLog->handle(
                action: AuditLogAction::StaffRoleChanged,
                entityType: 'branch_user',
                entityId: $scopedBranchUser->id,
                actorUser: $actor,
                organizationId: (int) $branch->organization_id,
                branchId: $branch->id,
                oldValues: [
                    'staff_user_id' => $scopedBranchUser->user_id,
                    'role_id' => $previousRoleId,
                    'role' => $currentRole->code->value,
                ],
                newValues: [
                    'staff_user_id' => $scopedBranchUser->user_id,
                    'role_id' => $assignableRole->id,
                    'role' => $assignableRole->code->value,
                    'reason' => $reason,
                ],
            );

            return $scopedBranchUser->refresh();
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

    private function validatedReason(string $reason): string
    {
        $reason = trim($reason);

        return (string) Validator::make(
            ['reason' => $reason],
            RestaurantValidationRules::auditReason('reason'),
        )->validate()['reason'];
    }
}
