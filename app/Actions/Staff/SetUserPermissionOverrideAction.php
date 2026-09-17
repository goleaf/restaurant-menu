<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class SetUserPermissionOverrideAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly EnsureOrganizationManagementRemainsAction $ensureManagerRemains,
    ) {}

    public function handle(User $user, Permission $permission, PermissionOverrideState $state, ?User $changedBy = null, ?int $organizationId = null, ?string $reason = null): void
    {
        if (! $changedBy instanceof User || ! is_int($organizationId)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($user, $permission, $state, $changedBy, $organizationId, $reason): void {
            $changedBy = $changedBy->fresh() ?? throw new AuthorizationException;
            $user = $user->fresh() ?? throw new AuthorizationException;
            $organization = Organization::query()->whereKey($organizationId)->firstOrFail();
            Gate::forUser($changedBy)->authorize('managePermissions', $organization);
            $membership = OrganizationUser::query()->where('organization_id', $organizationId)->where('user_id', $user->id)->firstOrFail();
            Gate::forUser($changedBy)->authorize('managePermissions', $membership);
            if ($user->isSuperadmin()) {
                throw new AuthorizationException;
            }
            $role = Role::query()->select(['id', 'code', 'name', 'sort_order'])->whereKey($membership->role_id)->firstOrFail();
            Gate::forUser($changedBy)->authorize('managePermissions', [$role, $organization]);
            $permission = Permission::query()->whereKey($permission->id)->firstOrFail();
            $reason = trim((string) $reason);
            if (SystemPermission::tryFrom($permission->code)?->isCritical()) {
                $reason = (string) Validator::make(['criticalPermissionChangeReason' => $reason], [
                    'criticalPermissionChangeReason' => ['required', 'string', 'min:3', 'max:500'],
                ], [
                    'criticalPermissionChangeReason.required' => __('permissions.errors.critical_reason_required'),
                    'criticalPermissionChangeReason.min' => __('permissions.errors.critical_reason_min'),
                ])->validate()['criticalPermissionChangeReason'];
            }
            $managedBefore = $permission->code === SystemPermission::ManageStaff->value && $user->hasPermission(SystemPermission::ManageStaff, $organization);
            $scopeKey = 'organization:'.$organizationId;
            $override = PermissionUserOverride::query()->where('user_id', $user->id)
                ->where('permission_id', $permission->id)->where('scope_key', $scopeKey)->first();
            $previous = ! $override instanceof PermissionUserOverride ? PermissionOverrideState::Default
                : ($override->enabled ? PermissionOverrideState::Allow : PermissionOverrideState::Deny);
            if ($previous === $state) {
                return;
            }
            if ($state === PermissionOverrideState::Default) {
                if ($override instanceof PermissionUserOverride && ! $override->delete()) {
                    throw new RuntimeException('Permission override removal was rejected.');
                }
            } else {
                $override ??= new PermissionUserOverride;
                $override->forceFill(['user_id' => $user->id, 'permission_id' => $permission->id,
                    'organization_id' => $organizationId, 'scope_key' => $scopeKey, 'enabled' => $state->enabledValue()]);
                if (! $override->save()) {
                    throw new RuntimeException('Permission override could not be saved.');
                }
            }
            if ($managedBefore) {
                $this->ensureManagerRemains->handle($organization);
            }
            $membership->forceFill(['access_version' => $membership->access_version + 1]);
            if (! $membership->save()) {
                throw new RuntimeException('Permission revision could not be saved.');
            }
            $this->recordAuditLog->handle(
                action: AuditLogAction::StaffPermissionChanged, entityType: 'staff_permission', entityId: $user->id,
                actorUser: $changedBy, organizationId: $organizationId,
                oldValues: ['staff_user_id' => $user->id, 'permission_code' => $permission->code, 'state' => $previous->value],
                newValues: ['staff_user_id' => $user->id, 'permission_code' => $permission->code, 'state' => $state->value, 'reason' => $reason === '' ? null : mb_substr($reason, 0, 500)],
            );
        });
    }
}
