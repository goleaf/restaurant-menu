<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RegisterInvitationRecipientAction
{
    public function __construct(private readonly AcceptInvitationAction $acceptInvitation) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(Invitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data): User {
            $recipient = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $this->acceptInvitation->handle($invitation, $recipient);

            return $recipient;
        }, 3);
    }
}
