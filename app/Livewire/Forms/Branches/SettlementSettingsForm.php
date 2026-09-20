<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Branches;

final class SettlementSettingsForm extends SettingsGroupForm
{
    public mixed $defaultCurrency = 'EUR';

    public mixed $serviceChargeEnabled = false;

    public mixed $serviceChargePercent = '0.00';

    public mixed $tipsEnabled = false;

    protected function group(): string
    {
        return 'settlement';
    }

    /** @return array<string, string> */
    protected function fields(): array
    {
        return [
            'defaultCurrency' => 'default_currency',
            'serviceChargeEnabled' => 'service_charge_enabled',
            'serviceChargePercent' => 'service_charge_percent',
            'tipsEnabled' => 'tips_enabled',
        ];
    }
}
