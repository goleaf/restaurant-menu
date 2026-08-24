<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\InvitationAccessState;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class ResolveInvitationAccessAction
{
    public function byToken(string $token, ?User $recipient): ResolvedInvitationAccess
    {
        $token = trim($token);

        if (strlen($token) !== 64 || ! ctype_alnum($token)) {
            return new ResolvedInvitationAccess(InvitationAccessState::Unavailable);
        }

        return $this->resolveAccess(
            $this->query()->where('invite_token_hash', hash('sha256', $token))->first(),
            $recipient,
        );
    }

    public function byId(int $invitationId, ?User $recipient): ResolvedInvitationAccess
    {
        return $this->resolveAccess($this->query()->whereKey($invitationId)->first(), $recipient);
    }

    /** @return Builder<Invitation> */
    private function query(): Builder
    {
        return Invitation::query()->select([
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
        ]);
    }

    private function resolveAccess(?Invitation $invitation, ?User $recipient): ResolvedInvitationAccess
    {
        if (! $invitation instanceof Invitation) {
            return new ResolvedInvitationAccess(InvitationAccessState::Unavailable);
        }

        $state = match (true) {
            $invitation->status === InvitationStatus::Accepted
                && $invitation->accepted_by_user_id !== null
                && $invitation->accepted_at !== null => InvitationAccessState::Accepted,
            $invitation->status === InvitationStatus::Expired => InvitationAccessState::Expired,
            $invitation->status === InvitationStatus::Pending
                && $invitation->expires_at->isPast() => InvitationAccessState::Expired,
            $invitation->canBeAccepted() => InvitationAccessState::Pending,
            default => InvitationAccessState::Unavailable,
        };

        if ($state === InvitationAccessState::Pending
            && $recipient instanceof User
            && Gate::forUser($recipient)->denies('view', $invitation)) {
            return new ResolvedInvitationAccess(InvitationAccessState::Unavailable, $invitation);
        }

        return new ResolvedInvitationAccess($state, $invitation);
    }
}
