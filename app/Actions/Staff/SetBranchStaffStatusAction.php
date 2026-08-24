<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class SetBranchStaffStatusAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function activate(BranchUser $membership, User $actor): BranchUser
    {
        $this->authorize($membership, $actor);

        $membership->forceFill([
            'status' => OrganizationUserStatus::Active,
            'assigned_at' => $membership->assigned_at ?? now(),
        ])->saveOrFail();

        return $membership;
    }

    public function suspend(BranchUser $membership, User $actor, string $reason): bool
    {
        $branch = $this->authorizeBranch($membership, $actor);

        if ($membership->user_id === $actor->id) {
            return false;
        }

        $this->authorizeRole($membership, $actor, $branch);

        $previousStatus = $membership->status;
        $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->saveOrFail();

        $this->recordAuditLog->handle(
            action: AuditLogAction::StaffDeactivated,
            entityType: 'branch_user',
            entityId: $membership->id,
            actorUser: $actor,
            organizationId: $membership->organization_id,
            branchId: $membership->branch_id,
            oldValues: [
                'staff_user_id' => $membership->user_id,
                'status' => $previousStatus,
            ],
            newValues: [
                'staff_user_id' => $membership->user_id,
                'status' => OrganizationUserStatus::Suspended,
                'reason' => $reason,
            ],
        );

        return true;
    }

    private function authorize(BranchUser $membership, User $actor): void
    {
        $branch = $this->authorizeBranch($membership, $actor);
        $this->authorizeRole($membership, $actor, $branch);
    }

    private function authorizeBranch(BranchUser $membership, User $actor): Branch
    {
        $branch = Branch::query()
            ->where('organization_id', $membership->organization_id)
            ->whereKey($membership->branch_id)
            ->firstOrFail();
        Gate::forUser($actor)->authorize('manageStaff', $branch);

        return $branch;
    }

    private function authorizeRole(BranchUser $membership, User $actor, Branch $branch): void
    {
        $organization = Organization::query()->whereKey($branch->organization_id)->firstOrFail();
        $role = Role::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($membership->role_id)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('assign', [$role, $organization]);
    }
}
