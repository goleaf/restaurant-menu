<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Models\BranchUser;
use App\Models\User;

final class SetBranchStaffStatusAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function activate(BranchUser $membership): BranchUser
    {
        $membership->forceFill([
            'status' => OrganizationUserStatus::Active,
            'assigned_at' => $membership->assigned_at ?? now(),
        ])->saveOrFail();

        return $membership;
    }

    public function suspend(BranchUser $membership, User $actor, string $reason): bool
    {
        if ($membership->user_id === $actor->id) {
            return false;
        }

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
}
