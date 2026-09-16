<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use App\Actions\Invitations\ResolveInvitationRecipientRoleAction;
use App\Models\Invitation;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Illuminate\Validation\Rules\Password;

final readonly class InvitationPagePresenter
{
    public function __construct(private ResolveInvitationRecipientRoleAction $recipientRole) {}

    /** @return array<string, mixed> */
    public function present(Invitation $invitation, ?User $recipient): array
    {
        $invitation->loadMissing([
            'organization:id,name',
            'branch:id,name',
            'brand:id,name',
            'role:id,code,name',
        ]);
        $role = ($recipient instanceof User ? $this->recipientRole->handle($invitation, $recipient) : null)
            ?? $invitation->role?->code;

        return [
            'title' => __('invitations.title'),
            'organizationName' => (string) $invitation->organization?->name,
            'branchName' => $invitation->branch?->name,
            'brandName' => $invitation->brand?->name,
            'roleName' => $role?->localizedLabel() ?? (string) $invitation->role?->name,
            'expiresAt' => LocalizedDateFormatter::dateTime($invitation->expires_at),
            'isAuthenticated' => $recipient instanceof User,
            'hasExistingAccount' => $recipient === null && User::query()->where('email', $invitation->email)->exists(),
            'accessExplanation' => $invitation->branch_id === null ? __('invitations.access.organization') : __('invitations.access.branch'),
            'invitationEmail' => $invitation->email,
            'invitationVersion' => $invitation->credentialVersion(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'acceptUrl' => route('invitations.accept'),
            'registerUrl' => route('invitations.register'),
            'loginUrl' => route('login'),
        ];
    }
}
