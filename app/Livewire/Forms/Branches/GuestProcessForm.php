<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Branches;

final class GuestProcessForm extends SettingsGroupForm
{
    public mixed $allowGuestCreatedSessions = true;

    public mixed $allowWaiterOpenedSessions = true;

    public mixed $allowGuestInviteLinks = true;

    protected function group(): string
    {
        return 'guest_process';
    }

    /** @return array<string, string> */
    protected function fields(): array
    {
        return [
            'allowGuestCreatedSessions' => 'allow_guest_created_sessions',
            'allowWaiterOpenedSessions' => 'allow_waiter_opened_sessions',
            'allowGuestInviteLinks' => 'allow_guest_invite_links',
        ];
    }
}
