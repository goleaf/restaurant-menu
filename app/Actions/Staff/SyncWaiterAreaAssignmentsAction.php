<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Staff\StaffQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SyncWaiterAreaAssignmentsAction
{
    public function __construct(
        private readonly StaffQueryService $staffQueries,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /** @param array<array-key,mixed> $areaNodeIds @return list<int> */
    public function handle(Branch $branch, BranchUser $membership, User $assignedBy, array $areaNodeIds, ?string $expectedFingerprint = null): array
    {
        $validated = Validator::make(['areaIds' => $areaNodeIds], [
            'areaIds' => ['array', 'max:500'], 'areaIds.*' => ['required', 'numeric', 'integer', 'min:1', 'distinct'],
        ])->validate();
        $ids = array_map(static fn (mixed $id): int => (int) $id, $validated['areaIds']);
        sort($ids);

        return DB::transaction(function () use ($branch, $membership, $assignedBy, $ids, $expectedFingerprint): array {
            $actor = $assignedBy->fresh() ?? $assignedBy;
            $currentBranch = Branch::query()->whereKey($branch->id)->where('organization_id', $branch->organization_id)->where('brand_id', $branch->brand_id)->firstOrFail();
            Gate::forUser($actor)->authorize('manageStaff', $currentBranch);
            $current = BranchUser::query()->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'access_version'])
                ->with('role:id,code,name,sort_order')->whereKey($membership->id)->where('organization_id', $currentBranch->organization_id)
                ->where('branch_id', $currentBranch->id)->where('user_id', $membership->user_id)->lockForUpdate()->firstOrFail();
            if ($current->status !== OrganizationUserStatus::Active || $current->role?->code !== SystemRole::Waiter
                || ! OrganizationUser::query()->where('organization_id', $currentBranch->organization_id)->where('user_id', $current->user_id)->where('status', OrganizationUserStatus::Active->value)->exists()) {
                throw new AuthorizationException;
            }
            if (User::query()->whereKey($current->user_id)->firstOrFail()->isSuperadmin()) {
                throw new AuthorizationException;
            }
            $organization = Organization::query()->whereKey($currentBranch->organization_id)->firstOrFail();
            Gate::forUser($actor)->authorize('assign', [$current->role, $organization]);
            $snapshot = $this->staffQueries->assignmentSnapshot($currentBranch, $current);
            if ($expectedFingerprint !== null && ! hash_equals($snapshot['fingerprint'], $expectedFingerprint)) {
                throw ValidationException::withMessages(['assignmentForm.areaIds' => __('staff.workspace.area_conflict')]);
            }
            $validIds = AreaNode::query()->where('branch_id', $currentBranch->id)->where('is_active', true)->whereIn('id', $ids)->pluck('id')->all();
            if (array_diff($ids, $validIds) !== []) {
                throw ValidationException::withMessages(['assignmentForm.areaIds' => __('staff.errors.zone_unavailable')]);
            }
            if ($ids === $snapshot['ids']) {
                return $ids;
            }
            if (BranchUser::query()->whereKey($current->id)->where('access_version', $current->access_version)
                ->update(['access_version' => $current->access_version + 1]) !== 1) {
                throw ValidationException::withMessages(['assignmentForm.areaIds' => __('staff.workspace.area_conflict')]);
            }
            AreaNodeWaiter::query()->where('organization_id', $currentBranch->organization_id)->where('branch_id', $currentBranch->id)
                ->where('user_id', $current->user_id)->whereNotIn('area_node_id', $ids)->delete();
            foreach (array_diff($ids, $snapshot['ids']) as $areaId) {
                $assignment = new AreaNodeWaiter;
                $assignment->forceFill(['organization_id' => $currentBranch->organization_id, 'branch_id' => $currentBranch->id,
                    'area_node_id' => $areaId, 'user_id' => $current->user_id, 'assigned_by_user_id' => $actor->id, 'assigned_at' => now()]);
                if (! $assignment->save()) {
                    throw new \RuntimeException('Area assignment was not saved.');
                }
            }
            $this->recordAuditLog->handle(
                action: AuditLogAction::StaffPermissionChanged,
                entityType: 'branch_user', entityId: $current->id, actorUser: $actor,
                organizationId: $currentBranch->organization_id, branchId: $currentBranch->id,
                oldValues: ['staff_user_id' => $current->user_id, 'scope' => 'areas', 'area_node_ids' => $snapshot['ids']],
                newValues: ['staff_user_id' => $current->user_id, 'scope' => 'areas', 'area_node_ids' => $ids],
            );

            return $ids;
        });
    }
}
