<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Support\Validation\Common\MoneyRules;
use App\Support\Validation\Common\RuleFields;

final class ModifierRules
{
    public const MAX_GROUPS = 50;

    public const MAX_OPTIONS_PER_GROUP = 50;

    /**
     * @return array<string, list<mixed>>
     */
    public static function modifierGroup(string $prefix = ''): array
    {
        return [
            RuleFields::name($prefix, 'modifierGroupName') => ['bail', 'required', 'string', 'max:160'],
            RuleFields::name($prefix, 'modifierGroupIsRequired') => ['boolean'],
            RuleFields::name($prefix, 'modifierGroupMinSelect') => ['required', 'numeric', 'integer', 'min:0', 'max:50'],
            RuleFields::name($prefix, 'modifierGroupMaxSelect') => [
                'required',
                'numeric',
                'integer',
                'min:0',
                'max:50',
                'gte:'.RuleFields::name($prefix, 'modifierGroupMinSelect'),
            ],
            RuleFields::name($prefix, 'modifierGroupSortOrder') => ['required', 'numeric', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function modifierOption(string $prefix = '', bool $canChangePrices = true, bool $canChangeAvailability = true): array
    {
        $rules = [
            RuleFields::name($prefix, 'modifierOptionName') => ['bail', 'required', 'string', 'max:160'],
            RuleFields::name($prefix, 'modifierOptionSortOrder') => ['required', 'numeric', 'integer', 'min:0', 'max:9999'],
        ];

        if ($canChangePrices) {
            $rules[RuleFields::name($prefix, 'modifierOptionPriceDelta')] = MoneyRules::amount(allowNegative: true);
        }

        if ($canChangeAvailability) {
            $rules[RuleFields::name($prefix, 'modifierOptionIsAvailable')] = ['boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function selectedModifierOptions(string $field = 'selectedModifierOptions'): array
    {
        return [
            $field => ['bail', 'array', 'max:'.self::MAX_GROUPS, new ModifierGroupKeys],
            $field.'.*' => ['bail', 'array', 'list', 'max:'.self::MAX_OPTIONS_PER_GROUP],
            $field.'.*.*' => ['bail', 'required', 'numeric', 'integer', 'min:1', 'distinct'],
        ];
    }
}
