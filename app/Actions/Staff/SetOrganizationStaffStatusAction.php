<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Models\OrganizationUser;
use App\Models\User;

final class SetOrganizationStaffStatusAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function activate(OrganizationUser $membership): OrganizationUser
    {
        $membership->forceFill([
            'status' => OrganizationUserStatus::Active,
            'joined_at' => $membership->joined_at ?? now(),
        ])->saveOrFail();

        return $membership;
    }

    public function suspend(OrganizationUser $membership, User $actor, string $reason): bool
    {
        if ($membership->user_id === $actor->id) {
            return false;
        }

        $previousStatus = $membership->status;
        $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->saveOrFail();

        $this->recordAuditLog->handle(
            action: AuditLogAction::StaffDeactivated,
            entityType: 'organization_user',
            entityId: $membership->id,
            actorUser: $actor,
            organizationId: $membership->organization_id,
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
