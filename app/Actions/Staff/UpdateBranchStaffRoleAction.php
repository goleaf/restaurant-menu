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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateBranchStaffRoleAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(User $actor, Branch $branch, BranchUser $branchUser, Role $role, string $reason, ?int $expectedVersion = null): BranchUser
    {
        $reason = $this->validatedReason($reason);
        $expectedVersion ??= (int) $branchUser->getAttribute('access_version');

        return DB::transaction(function () use ($actor, $branch, $branchUser, $role, $reason, $expectedVersion): BranchUser {
            $actor = User::query()->with('roles')->whereKey($actor->id)->firstOrFail();
            $branch = Branch::query()->whereKey($branch->id)->where('organization_id', $branch->organization_id)->firstOrFail();
            $organization = Organization::query()
                ->select(['id', 'owner_user_id', 'name', 'slug', 'default_locale', 'timezone', 'currency_code', 'status', 'created_at', 'updated_at', 'deleted_at'])
                ->whereKey($branch->organization_id)
                ->firstOrFail();
            $scopedBranchUser = BranchUser::query()
                ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'assigned_at', 'assigned_by_user_id', 'created_at', 'updated_at', 'access_version'])
                ->where('organization_id', $branch->organization_id)
                ->where('branch_id', $branch->id)
                ->whereKey($branchUser->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('manageStaff', $branch);
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
            Gate::forUser($actor)->authorize('assign', [$currentRole, $organization]);
            if ($scopedBranchUser->user_id !== $branchUser->user_id
                || User::query()->whereKey($scopedBranchUser->user_id)->whereHas('roles', fn ($query) => $query->where('code', SystemRole::Superadmin->value))->exists()) {
                throw new AuthorizationException;
            }
            if (BranchUser::query()->whereKey($scopedBranchUser->id)->where('access_version', $expectedVersion)
                ->update(['access_version' => $expectedVersion + 1]) !== 1) {
                throw ValidationException::withMessages(['editingRoleId' => __('staff.errors.stale_membership')]);
            }

            $previousRoleId = (int) $scopedBranchUser->role_id;

            $scopedBranchUser->forceFill(['role_id' => $assignableRole->id, 'access_version' => $expectedVersion + 1]);
            if (! $scopedBranchUser->save()) {
                throw new \RuntimeException('Staff role change was rejected.');
            }

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

            return $scopedBranchUser;
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
