<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Branches;

final class AdvancedSettingsForm extends SettingsGroupForm
{
    public mixed $pollingIntervalSeconds = 1;

    public mixed $inactivityWarningMinutes = 45;

    public mixed $pendingSessionExpireMinutes = 30;

    protected function group(): string
    {
        return 'advanced';
    }

    /** @return array<string, string> */
    protected function fields(): array
    {
        return [
            'pollingIntervalSeconds' => 'polling_interval_seconds',
            'inactivityWarningMinutes' => 'inactivity_warning_minutes',
            'pendingSessionExpireMinutes' => 'pending_session_expire_minutes',
        ];
    }
}
