<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Invitations\CancelPendingMemberInvitationsAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Validation\Common\AuditReasonRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SetOrganizationStaffStatusAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly CancelPendingMemberInvitationsAction $cancelPendingInvitations,
        private readonly EnsureOrganizationManagementRemainsAction $ensureManagerRemains,
    ) {}

    public function activate(OrganizationUser $membership, User $actor, ?string $reason = null, ?int $expectedVersion = null): OrganizationUser
    {
        return $this->change($membership, $actor, OrganizationUserStatus::Active, $reason, $expectedVersion);
    }

    public function suspend(OrganizationUser $membership, User $actor, string $reason, ?int $expectedVersion = null): bool
    {
        $this->change($membership, $actor, OrganizationUserStatus::Suspended, $reason, $expectedVersion);

        return true;
    }

    private function change(OrganizationUser $membership, User $actor, OrganizationUserStatus $target, ?string $reason, ?int $expectedVersion): OrganizationUser
    {
        $expectedVersion ??= (int) $membership->getAttribute('access_version');

        return DB::transaction(function () use ($membership, $actor, $target, $reason, $expectedVersion): OrganizationUser {
            $actor = User::query()->with('roles')->whereKey($actor->id)->firstOrFail();
            $current = OrganizationUser::query()->whereKey($membership->id)
                ->where('organization_id', $membership->organization_id)->where('user_id', $membership->user_id)->firstOrFail();
            $organization = Organization::query()->whereKey($current->organization_id)->firstOrFail();
            Gate::forUser($actor)->authorize('manageStaff', $organization);
            $role = Role::query()->select(['id', 'code', 'name', 'sort_order'])->whereKey($current->role_id)->firstOrFail();
            Gate::forUser($actor)->authorize('assign', [$role, $organization]);
            $staff = User::query()->whereKey($current->user_id)->firstOrFail();
            if ($current->user_id === $actor->id || $staff->isSuperadmin()) {
                throw ValidationException::withMessages(['reason' => __('staff.errors.self_role_change_blocked')]);
            }
            if ($current->status === $target) {
                return $current;
            }
            if ($current->status === OrganizationUserStatus::Invited || $current->status === OrganizationUserStatus::Removed) {
                throw ValidationException::withMessages(['reason' => __('staff.errors.membership_unavailable')]);
            }
            $reason = trim((string) $reason);
            if ($target === OrganizationUserStatus::Suspended || $reason !== '') {
                $reason = (string) Validator::make(['reason' => $reason], AuditReasonRules::auditReason('reason'))->validate()['reason'];
            }
            if (OrganizationUser::query()->whereKey($current->id)->where('access_version', $expectedVersion)
                ->update(['access_version' => $expectedVersion + 1]) !== 1) {
                throw ValidationException::withMessages(['reason' => __('staff.errors.stale_membership')]);
            }

            if ($target === OrganizationUserStatus::Suspended && $role->code === SystemRole::Owner
                && ! OrganizationUser::query()->where('organization_id', $organization->id)
                    ->whereKeyNot($current->id)->where('status', OrganizationUserStatus::Active->value)
                    ->whereHas('role', fn ($query) => $query->where('code', SystemRole::Owner->value))->exists()) {
                throw ValidationException::withMessages(['reason' => __('staff.errors.last_owner_role_change_blocked')]);
            }

            $previousStatus = $current->status;
            $current->forceFill(['status' => $target, 'access_version' => $expectedVersion + 1]);
            if (! $current->save()) {
                throw new RuntimeException('Staff membership change was rejected.');
            }
            if ($target === OrganizationUserStatus::Suspended) {
                $this->ensureManagerRemains->handle($organization);
                $this->cancelPendingInvitations->handle($organization, $staff, null, $actor);
            }
            $this->recordAuditLog->handle(
                action: $target === OrganizationUserStatus::Active ? AuditLogAction::StaffReactivated : AuditLogAction::StaffDeactivated,
                entityType: 'organization_user',
                entityId: $current->id,
                actorUser: $actor,
                organizationId: $current->organization_id,

                oldValues: ['staff_user_id' => $current->user_id, 'status' => $previousStatus],
                newValues: ['staff_user_id' => $current->user_id, 'status' => $target, 'reason' => $reason],
            );

            return $current;
        });
    }
}
