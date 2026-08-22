<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

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

            Gate::forUser($recipient)->authorize('accept', $lockedInvitation);

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

            $lockedInvitation->forceFill([
                'status' => InvitationStatus::Accepted,
                'accepted_by_user_id' => $recipient->id,
                'accepted_at' => now(),
            ])->save();

            return $lockedInvitation;
        }, 3);
    }
}
