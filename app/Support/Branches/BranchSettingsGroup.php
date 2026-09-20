<?php

declare(strict_types=1);

namespace App\Support\Branches;

use App\Models\Branch;
use App\Models\BranchSetting;
use InvalidArgumentException;

final class BranchSettingsGroup
{
    /** @return list<string> */
    public static function fields(string $group): array
    {
        return match ($group) {
            'guest_process' => ['allow_guest_created_sessions', 'allow_waiter_opened_sessions', 'allow_guest_invite_links'],
            'settlement' => ['default_currency', 'service_charge_enabled', 'service_charge_basis_points', 'tips_enabled'],
            'locale' => ['default_language'],
            'advanced' => ['polling_interval_seconds', 'inactivity_warning_minutes', 'pending_session_expire_minutes'],
            default => throw new InvalidArgumentException('Unknown restaurant settings group.'),
        };
    }

    /** @return array<string, mixed> */
    public static function values(BranchSetting $settings, string $group): array
    {
        $values = [];
        $attributes = $settings->getAttributes();
        foreach (self::fields($group) as $field) {
            $value = $attributes[$field] ?? null;
            $values[$field] = ($settings->getCasts()[$field] ?? null) === 'boolean'
                && in_array($value, [0, 1, '0', '1', false, true], true)
                ? (bool) $value : $value;
        }

        return $values;
    }

    public static function fingerprint(Branch $branch, BranchSetting $settings, string $group): string
    {
        $fields = self::fields($group);
        $raw = $settings->getAttributes();
        $values = array_intersect_key($raw, array_flip($fields));
        foreach ($values as $field => $value) {
            if (is_bool($value)) {
                $values[$field] = (int) $value;
            }
        }
        if ($group === 'settlement') {
            $values['branch_currency'] = $branch->getRawOriginal('currency');
        }

        ksort($values);

        return hash('sha256', json_encode([$branch->id, $group, $values], JSON_THROW_ON_ERROR));
    }
}
