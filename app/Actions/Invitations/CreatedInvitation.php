<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Invitation;

final readonly class CreatedInvitation
{
    public function __construct(
        public Invitation $invitation,
        public string $token,
        public string $code,
    ) {}

    public function inviteLink(): string
    {
        return route('invitations.show', ['token' => $this->token]);
    }
}
