<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Models\Branch;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CancelPendingMemberInvitationsAction
{
    public function __construct(private readonly RecordAuditLogAction $recordAuditLog) {}

    /** Called inside the authorized membership status transaction. */
    public function handle(Organization $organization, User $target, ?Branch $branch, User $actor): int
    {
        if (DB::transactionLevel() === 0 || ($branch !== null && $branch->organization_id !== $organization->id)) {
            throw new DomainException('Invitation cancellation requires its membership transaction.');
        }

        $cancelled = 0;
        $invitations = Invitation::query()->where('organization_id', $organization->id)
            ->where('email', Str::lower(trim($target->email)))
            ->whereIn('status', [InvitationStatus::Pending->value, InvitationStatus::Expired->value])
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->lazyById(100);

        foreach ($invitations as $invitation) {
            $previousStatus = $invitation->status;
            $invitation->forceFill(['status' => InvitationStatus::Cancelled, 'invite_token_hash' => null, 'invite_code_hash' => null]);
            if (! $invitation->save()) {
                throw new DomainException('Pending invitation could not be cancelled.');
            }
            $this->recordAuditLog->handle(
                action: AuditLogAction::InvitationCancelled,
                entityType: 'invitation', entityId: $invitation->id, actorUser: $actor,
                organizationId: $organization->id, branchId: $invitation->branch_id,
                oldValues: ['status' => $previousStatus->value],
                newValues: ['status' => InvitationStatus::Cancelled->value, 'reason' => 'membership_suspended'],
            );
            $cancelled++;
        }

        return $cancelled;
    }
}
