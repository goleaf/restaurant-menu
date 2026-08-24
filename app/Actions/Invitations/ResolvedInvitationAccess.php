<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\InvitationAccessState;
use App\Models\Invitation;

final readonly class ResolvedInvitationAccess
{
    public function __construct(
        public InvitationAccessState $state,
        public ?Invitation $invitation = null,
    ) {}
}
