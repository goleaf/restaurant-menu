<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AcceptInvitationAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly EnsureInvitationScopeAction $ensureScope,
    ) {}

    public function handle(Invitation $invitation, User $recipient): Invitation
    {
        return DB::transaction(function () use ($invitation, $recipient): Invitation {
            $recipient = User::query()->whereKey($recipient->id)->first();
            if (! $recipient instanceof User) {
                throw new DomainException('Invitation recipient is no longer available.');
            }
            $lockedInvitation = Invitation::query()
                ->select([
                    'id',
                    'organization_id',
                    'brand_id',
                    'branch_id',
                    'role_id',
                    'email',
                    'phone',
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
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedInvitation instanceof Invitation
                || ! $lockedInvitation->matchesCredential($invitation->invite_token_hash)) {
                throw new DomainException('Invitation is no longer available.');
            }

            $this->ensureScope->handle($lockedInvitation);

            Gate::forUser($recipient)->authorize('accept', $lockedInvitation);

            $membership = OrganizationUser::query()
                ->where('organization_id', $lockedInvitation->organization_id)
                ->where('user_id', $recipient->id)
                ->lockForUpdate()->first();
            $branchMembership = $lockedInvitation->branch_id === null ? null : BranchUser::query()
                ->where('organization_id', $lockedInvitation->organization_id)
                ->where('branch_id', $lockedInvitation->branch_id)
                ->where('user_id', $recipient->id)
                ->lockForUpdate()->first();

            if (($membership instanceof OrganizationUser && $membership->status !== OrganizationUserStatus::Active)
                || ($branchMembership instanceof BranchUser && $branchMembership->status !== OrganizationUserStatus::Active)) {
                throw new DomainException('Invitation cannot restore suspended membership.');
            }

            if ($lockedInvitation->status === InvitationStatus::Accepted
                && $lockedInvitation->accepted_by_user_id === $recipient->id
                && $membership instanceof OrganizationUser
                && ($lockedInvitation->branch_id === null || $branchMembership instanceof BranchUser)) {
                return $lockedInvitation;
            }

            if (! $lockedInvitation->canBeAccepted()) {
                throw new DomainException('Invitation is no longer available.');
            }

            $acceptedAt = now();
            $acceptedCount = Invitation::query()
                ->whereKey($lockedInvitation->id)
                ->where('status', InvitationStatus::Pending->value)
                ->where('invite_token_hash', $invitation->invite_token_hash)
                ->where('expires_at', '>', $acceptedAt)
                ->whereNull('accepted_by_user_id')
                ->whereNull('accepted_at')
                ->update([
                    'status' => InvitationStatus::Accepted->value,
                    'accepted_by_user_id' => $recipient->id,
                    'accepted_at' => $acceptedAt,
                    'updated_at' => $acceptedAt,
                ]);

            if ($acceptedCount !== 1) {
                throw new DomainException('Invitation is no longer available.');
            }

            $lockedInvitation->refresh();

            $membership ??= new OrganizationUser;

            $membership->forceFill([
                'organization_id' => $lockedInvitation->organization_id,
                'user_id' => $recipient->id,
                'role_id' => $membership->role_id ?? $lockedInvitation->role_id,
                'status' => OrganizationUserStatus::Active,
                'joined_at' => $membership->joined_at ?? now(),
                'invited_by_user_id' => $membership->invited_by_user_id ?? $lockedInvitation->invited_by_user_id,
            ]);

            if (! $membership->save()) {
                throw new DomainException('Invitation membership could not be saved.');
            }

            if ($lockedInvitation->branch_id !== null) {
                $branchMembership ??= new BranchUser;

                $branchMembership->forceFill([
                    'organization_id' => $lockedInvitation->organization_id,
                    'branch_id' => $lockedInvitation->branch_id,
                    'user_id' => $recipient->id,
                    'role_id' => $branchMembership->role_id ?? $lockedInvitation->role_id,
                    'status' => OrganizationUserStatus::Active,
                    'assigned_at' => $branchMembership->assigned_at ?? now(),
                    'assigned_by_user_id' => $branchMembership->assigned_by_user_id ?? $lockedInvitation->invited_by_user_id,
                ]);

                if (! $branchMembership->save()) {
                    throw new DomainException('Invitation branch membership could not be saved.');
                }
            }

            $audit = $this->recordAuditLog->handle(
                action: AuditLogAction::InvitationAccepted,
                entityType: 'invitation',
                entityId: $lockedInvitation->id,
                actorUser: $recipient,
                organizationId: $lockedInvitation->organization_id,
                branchId: $lockedInvitation->branch_id,
                oldValues: [
                    'status' => InvitationStatus::Pending->value,
                ],
                newValues: [
                    'status' => InvitationStatus::Accepted->value,
                    'accepted_by_user_id' => $recipient->id,
                    'role_id' => $lockedInvitation->role_id,
                ],
            );

            if (! $audit->exists) {
                throw new DomainException('Invitation acceptance could not be recorded.');
            }

            return $lockedInvitation;
        }, 3);
    }
}
