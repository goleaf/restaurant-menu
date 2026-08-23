<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CancelInvitationAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(User $actor, Organization $organization, Invitation $invitation): Invitation
    {
        Gate::forUser($actor)->authorize('manageStaff', $organization);

        return DB::transaction(function () use ($actor, $organization, $invitation): Invitation {
            $scopedInvitation = Invitation::query()
                ->select([
                    'id',
                    'organization_id',
                    'brand_id',
                    'branch_id',
                    'role_id',
                    'email',
                    'phone',
                    'invite_token',
                    'invite_code',
                    'invite_token_hash',
                    'invite_code_hash',
                    'expires_at',
                    'status',
                    'invited_by_user_id',
                    'accepted_by_user_id',
                    'accepted_at',
                    'created_at',
                    'updated_at',
                ])
                ->where('organization_id', $organization->id)
                ->where('brand_id', $invitation->brand_id)
                ->where('branch_id', $invitation->branch_id)
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($scopedInvitation->status !== InvitationStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => __('staff.errors.invitation_not_pending'),
                ]);
            }

            $scopedInvitation->forceFill([
                'status' => InvitationStatus::Cancelled,
                'invite_token' => null,
                'invite_code' => null,
                'invite_token_hash' => null,
                'invite_code_hash' => null,
                'accepted_by_user_id' => null,
                'accepted_at' => null,
            ])->saveOrFail();

            $this->recordAuditLog->handle(
                action: AuditLogAction::InvitationCancelled,
                entityType: 'invitation',
                entityId: $scopedInvitation->id,
                actorUser: $actor,
                organizationId: $organization->id,
                branchId: $scopedInvitation->branch_id,
                oldValues: [
                    'status' => InvitationStatus::Pending->value,
                    'email' => $scopedInvitation->email,
                    'phone' => $scopedInvitation->phone,
                ],
                newValues: [
                    'status' => InvitationStatus::Cancelled->value,
                    'email' => $scopedInvitation->email,
                    'phone' => $scopedInvitation->phone,
                ],
            );

            return $scopedInvitation->refresh();
        });
    }
}
