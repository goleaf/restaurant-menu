<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\User;

final class ResolveInvitationRecipientRoleAction
{
    public function handle(Invitation $invitation, User $recipient): ?SystemRole
    {
        $query = $invitation->branch_id === null
            ? OrganizationUser::query()
            : BranchUser::query()->where('branch_id', $invitation->branch_id);
        $membership = $query->select(['id', 'role_id'])
            ->with('role:id,code')
            ->where('organization_id', $invitation->organization_id)
            ->where('user_id', $recipient->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->first();

        return $membership?->role?->code;
    }
}
