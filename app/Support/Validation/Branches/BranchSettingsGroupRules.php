<?php

declare(strict_types=1);

namespace App\Support\Validation\Branches;

use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Support\Validation\DecimalMoney;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class BranchSettingsGroupRules
{
    /** @return array<string, list<mixed>> */
    public static function for(string $group): array
    {
        return match ($group) {
            'guest_process' => [
                'allow_guest_created_sessions' => ['required', 'boolean'],
                'allow_waiter_opened_sessions' => ['required', 'boolean'],
                'allow_guest_invite_links' => ['required', 'boolean'],
            ],
            'settlement' => [
                'default_currency' => ['required', 'string', Rule::in(SupportedCurrency::values())],
                'service_charge_enabled' => ['required', 'boolean'],
                'service_charge_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2', new DecimalMoney],
                'tips_enabled' => ['required', 'boolean'],
            ],
            'locale' => ['default_language' => ['required', 'string', Rule::in(SupportedLocale::values())]],
            'advanced' => [
                'polling_interval_seconds' => ['required', 'numeric', 'integer', 'min:1', 'max:60'],
                'inactivity_warning_minutes' => ['required', 'numeric', 'integer', 'min:1', 'max:1440'],
                'pending_session_expire_minutes' => ['required', 'numeric', 'integer', 'min:1', 'max:1440'],
            ],
            default => throw new InvalidArgumentException('Unknown restaurant settings group.'),
        };
    }

    /** @return array<string, string> */
    public static function attributes(string $group): array
    {
        $attributes = [];
        foreach (array_keys(self::for($group)) as $field) {
            $attributes[$field] = __('settings.fields.'.$field);
        }

        return $attributes;
    }
}
