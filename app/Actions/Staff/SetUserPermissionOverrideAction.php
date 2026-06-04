<?php

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\PermissionOverrideState;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetUserPermissionOverrideAction
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
    ): void {
        DB::transaction(function () use ($user, $permission, $state, $changedBy, $organizationId): void {
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
}
