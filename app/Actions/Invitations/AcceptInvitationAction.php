<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AcceptInvitationAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(Invitation $invitation, User $recipient): Invitation
    {
        return DB::transaction(function () use ($invitation, $recipient): Invitation {
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

            if (! $lockedInvitation instanceof Invitation || ! $lockedInvitation->canBeAccepted()) {
                throw new DomainException('Invitation is no longer available.');
            }

            $this->ensureInvitationScopeCanBeAccepted($lockedInvitation);

            Gate::forUser($recipient)->authorize('accept', $lockedInvitation);

            $acceptedAt = now();
            $acceptedCount = Invitation::query()
                ->whereKey($lockedInvitation->id)
                ->where('status', InvitationStatus::Pending->value)
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

            $recipient->roles()->syncWithoutDetachingOrFail([$lockedInvitation->role_id]);

            $membership = OrganizationUser::query()
                ->where('organization_id', $lockedInvitation->organization_id)
                ->where('user_id', $recipient->id)
                ->lockForUpdate()
                ->first() ?? new OrganizationUser;

            $membership->forceFill([
                'organization_id' => $lockedInvitation->organization_id,
                'user_id' => $recipient->id,
                'role_id' => $lockedInvitation->branch_id === null
                    ? $lockedInvitation->role_id
                    : ($membership->role_id ?? $lockedInvitation->role_id),
                'status' => OrganizationUserStatus::Active,
                'joined_at' => $membership->joined_at ?? now(),
                'invited_by_user_id' => $membership->invited_by_user_id ?? $lockedInvitation->invited_by_user_id,
            ])->save();

            if ($lockedInvitation->branch_id !== null) {
                $branchMembership = BranchUser::query()
                    ->where('branch_id', $lockedInvitation->branch_id)
                    ->where('user_id', $recipient->id)
                    ->lockForUpdate()
                    ->first() ?? new BranchUser;

                $branchMembership->forceFill([
                    'organization_id' => $lockedInvitation->organization_id,
                    'branch_id' => $lockedInvitation->branch_id,
                    'user_id' => $recipient->id,
                    'role_id' => $lockedInvitation->role_id,
                    'status' => OrganizationUserStatus::Active,
                    'assigned_at' => $branchMembership->assigned_at ?? now(),
                    'assigned_by_user_id' => $branchMembership->assigned_by_user_id ?? $lockedInvitation->invited_by_user_id,
                ])->save();
            }

            $this->recordAuditLog->handle(
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

            return $lockedInvitation;
        }, 3);
    }

    private function ensureInvitationScopeCanBeAccepted(Invitation $invitation): void
    {
        if (! is_string($invitation->email) || trim($invitation->email) === '') {
            throw new DomainException('Invitation recipient is not bound.');
        }

        $role = Role::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($invitation->role_id)
            ->first();

        if (! $role instanceof Role || $role->code === SystemRole::Superadmin) {
            throw new DomainException('Invitation role is not available.');
        }

        if (! Organization::query()->whereKey($invitation->organization_id)->exists()) {
            throw new DomainException('Invitation organization is not available.');
        }

        if ($invitation->brand_id !== null && ! Brand::query()
            ->where('organization_id', $invitation->organization_id)
            ->whereKey($invitation->brand_id)
            ->exists()) {
            throw new DomainException('Invitation brand is not available.');
        }

        if ($invitation->branch_id === null) {
            return;
        }

        if ($invitation->brand_id === null || ! Branch::query()
            ->where('organization_id', $invitation->organization_id)
            ->where('brand_id', $invitation->brand_id)
            ->whereKey($invitation->branch_id)
            ->exists()) {
            throw new DomainException('Invitation branch is not available.');
        }
    }
}
