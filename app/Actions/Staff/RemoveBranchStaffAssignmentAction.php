<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Invitations\CancelPendingMemberInvitationsAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Staff\BranchAssignmentQueryService;
use App\Support\Validation\Common\AuditReasonRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RemoveBranchStaffAssignmentAction
{
    public function __construct(
        private readonly BranchAssignmentQueryService $assignments,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly CancelPendingMemberInvitationsAction $cancelPendingInvitations,
    ) {}

    public function handle(Organization $organization, Branch $branch, OrganizationUser $membership, User $actor, string $expectedAccessFingerprint, string $reason, bool $confirmed): OrganizationUser
    {
        $validated = Validator::make(['reason' => trim($reason), 'confirmed' => $confirmed], [
            ...AuditReasonRules::auditReason('reason'), 'confirmed' => ['accepted'],
        ], attributes: ['reason' => __('validation.attributes.reason'), 'confirmed' => __('team.card.confirm_scope')])->validate();

        return DB::transaction(function () use ($organization, $branch, $membership, $actor, $expectedAccessFingerprint, $validated): OrganizationUser {
            $organization = Organization::query()->whereKey($organization->id)->firstOrFail();
            $branch = Branch::query()->where('organization_id', $organization->id)->where('brand_id', $branch->brand_id)->whereKey($branch->id)->firstOrFail();
            $actor = User::query()->with('roles')->whereKey($actor->id)->firstOrFail();
            $current = OrganizationUser::query()->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])->with('user')
                ->where('organization_id', $organization->id)->where('user_id', $membership->user_id)->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $preview = $this->assignments->removalPreview($organization, $branch, $current, $actor);
            if (! $preview['can_apply']) {
                throw new AuthorizationException;
            }
            if (! hash_equals($preview['fingerprint'], $expectedAccessFingerprint)) {
                throw ValidationException::withMessages(['organizationMembershipId' => __('staff.errors.stale_membership')]);
            }
            $assignment = BranchUser::query()->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'access_version'])
                ->where('organization_id', $organization->id)->where('branch_id', $branch->id)->where('user_id', $current->user_id)
                ->whereKey($preview['branch_assignment_id'])->where('access_version', $preview['access_version'])->lockForUpdate()->firstOrFail();
            $beforeIds = BranchUser::query()->where('organization_id', $organization->id)->where('user_id', $current->user_id)
                ->where('status', OrganizationUserStatus::Active->value)->orderBy('branch_id')->pluck('branch_id')->all();
            $afterIds = $preview['operation'] === 'return_to_organization'
                ? Branch::query()->where('organization_id', $organization->id)->orderBy('id')->pluck('id')->all()
                : array_values(array_diff($beforeIds, [$branch->id]));
            if (OrganizationUser::query()->whereKey($current->id)->where('access_version', $current->access_version)
                ->update(['access_version' => $current->access_version + 1]) !== 1) {
                throw ValidationException::withMessages(['organizationMembershipId' => __('staff.errors.stale_membership')]);
            }
            AreaNodeWaiter::query()->where('organization_id', $organization->id)->where('branch_id', $branch->id)->where('user_id', $current->user_id)->delete();
            if (! $assignment->delete()) {
                throw new RuntimeException('Branch assignment could not be removed.');
            }
            $this->cancelPendingInvitations->handle($organization, $current->user, $branch, $actor, 'branch_assignment_removed');
            $identity = [
                'scope' => 'branch_assignment_removed', 'staff_user_id' => $current->user_id, 'user_id' => $current->user_id,
                'branch_assignment_id' => $assignment->id, 'branch_id' => $branch->id,
            ];
            $this->recordAuditLog->handle(
                action: AuditLogAction::StaffPermissionChanged,
                entityType: 'organization_user', entityId: $current->id, actorUser: $actor,
                organizationId: $organization->id, branchId: $branch->id,
                oldValues: [...$identity, 'branch_access_mode' => 'assignments', 'branch_ids' => $beforeIds, 'area_count' => $preview['area_count']],
                newValues: [...$identity, 'branch_access_mode' => $preview['operation'] === 'return_to_organization' ? 'organization' : 'assignments', 'branch_ids' => $afterIds, 'area_count' => 0, 'reason' => $validated['reason']],
            );

            return $current->refresh();
        });
    }
}
