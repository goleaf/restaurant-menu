<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class RegisterInvitationRecipientAction
{
    public function __construct(private readonly AcceptInvitationAction $acceptInvitation) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(Invitation $invitation, array $data): User
    {
        $data['email'] = Str::lower(trim($data['email']));
        $data['name'] = trim($data['name']);

        return DB::transaction(function () use ($invitation, $data): User {
            $expectedDigest = $invitation->invite_token_hash;
            $invitation = Invitation::query()
                ->select([
                    'id',
                    'organization_id',
                    'brand_id',
                    'branch_id',
                    'role_id',
                    'email',
                    'status',
                    'expires_at',
                    'invited_by_user_id',
                    'accepted_by_user_id',
                    'accepted_at',
                    'invite_token_hash',
                ])
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invitation->matchesCredential($expectedDigest)) {
                throw new DomainException('Invitation registration is no longer available.');
            }
            $existingRecipient = User::query()
                ->select(['id', 'name', 'email', 'password'])
                ->where('email', $data['email'])
                ->lockForUpdate()
                ->first();

            if ($existingRecipient instanceof User) {
                if ($invitation->status === InvitationStatus::Accepted
                    && $invitation->accepted_by_user_id === $existingRecipient->id
                    && Hash::check($data['password'], $existingRecipient->password)) {
                    $this->acceptInvitation->handle($invitation, $existingRecipient);

                    return $existingRecipient;
                }

                throw new DomainException('Invitation registration is no longer available.');
            }

            $recipient = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            if (! $recipient->exists) {
                throw new DomainException('Invitation account could not be saved.');
            }

            $this->acceptInvitation->handle($invitation, $recipient);

            return $recipient;
        }, 3);
    }
}
