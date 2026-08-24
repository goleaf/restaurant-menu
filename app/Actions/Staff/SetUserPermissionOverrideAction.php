<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\PermissionOverrideState;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SetUserPermissionOverrideAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(
        User $user,
        Permission $permission,
        PermissionOverrideState $state,
        ?User $changedBy = null,
        ?int $organizationId = null,
        ?string $reason = null,
    ): void {
        if (! $changedBy instanceof User || ! is_int($organizationId)) {
            throw new AuthorizationException;
        }

        $organization = Organization::query()->whereKey($organizationId)->firstOrFail();
        Gate::forUser($changedBy)->authorize('managePermissions', $organization);
        $membership = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        Gate::forUser($changedBy)->authorize('managePermissions', $membership);

        if ($user->isSuperadmin()) {
            throw new AuthorizationException;
        }

        $role = Role::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($membership->role_id)
            ->firstOrFail();
        Gate::forUser($changedBy)->authorize('managePermissions', [$role, $organization]);

        DB::transaction(function () use ($user, $permission, $state, $changedBy, $organizationId, $reason): void {
            $previousState = $this->currentState($user, $permission);
            $enabled = $state->enabledValue();

            if ($enabled === null) {
                $user->permissionOverrides()->detach($permission->id);
            } else {
                $user->permissionOverrides()->syncWithoutDetachingOrFail([
                    $permission->id => ['enabled' => $enabled],
                ]);
            }

            if ($previousState === $state) {
                return;
            }

            $this->recordAuditLog->handle(
                action: AuditLogAction::StaffPermissionChanged,
                entityType: 'staff_permission',
                entityId: $user->id,
                actorUser: $changedBy,
                organizationId: $organizationId,
                oldValues: [
                    'staff_user_id' => $user->id,
                    'permission_code' => $permission->code,
                    'state' => $previousState->value,
                ],
                newValues: [
                    'staff_user_id' => $user->id,
                    'permission_code' => $permission->code,
                    'state' => $state->value,
                    'reason' => $this->normalizeReason($reason),
                ],
            );
        });
    }

    private function currentState(User $user, Permission $permission): PermissionOverrideState
    {
        $override = $user->permissionOverrides()
            ->select(['permissions.id', 'permissions.code'])
            ->where('permissions.id', $permission->id)
            ->first();

        if (! $override instanceof Permission) {
            return PermissionOverrideState::Default;
        }

        return (bool) $override->pivot->enabled
            ? PermissionOverrideState::Allow
            : PermissionOverrideState::Deny;
    }

    private function normalizeReason(?string $reason): ?string
    {
        $normalized = trim((string) $reason);

        return $normalized === '' ? null : mb_substr($normalized, 0, 500);
    }
}
