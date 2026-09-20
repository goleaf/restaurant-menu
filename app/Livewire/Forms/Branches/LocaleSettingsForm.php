<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Branches;

final class LocaleSettingsForm extends SettingsGroupForm
{
    public mixed $defaultLanguage = 'en';

    protected function group(): string
    {
        return 'locale';
    }

    /** @return array<string, string> */
    protected function fields(): array
    {
        return [
            'defaultLanguage' => 'default_language',
        ];
    }
}
