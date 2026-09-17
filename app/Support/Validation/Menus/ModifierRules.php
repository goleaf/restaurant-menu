<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Enums\SupportedLocale;
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
                new ModifierSelectionLimit(RuleFields::name($prefix, 'modifierGroupMinSelect')),
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

    /** @return array<string,string> */
    public static function groupValidationAttributes(): array
    {
        $attributes = ['modifierGroupName' => __('menu.translations.name', ['language' => SupportedLocale::English->label()]),
            'modifierGroupIsRequired' => __('guest.cart.required'),
            'modifierGroupMinSelect' => __('ui.organizations.brands.branches.menu.index.min'),
            'modifierGroupMaxSelect' => __('ui.organizations.brands.branches.menu.index.max'),
            'modifierGroupSortOrder' => __('ui.departments.dashboard.sort'), 'modifierGroupTranslations' => __('menu.translations.heading')];
        foreach (SupportedLocale::labels() as $locale => $language) {
            $attributes['modifierGroupTranslations.'.$locale] = __('menu.translations.name', ['language' => $language]);
        }

        return $attributes;
    }

    /** @return array<string,string> */
    public static function optionValidationAttributes(): array
    {
        $attributes = ['modifierOptionName' => __('menu.translations.name', ['language' => SupportedLocale::English->label()]),
            'modifierOptionPriceDelta' => __('guest.cart.price'),
            'modifierOptionIsAvailable' => __('menu.guest.available'),
            'modifierOptionSortOrder' => __('ui.departments.dashboard.sort'), 'modifierOptionTranslations' => __('menu.translations.heading')];
        foreach (SupportedLocale::labels() as $locale => $language) {
            $attributes['modifierOptionTranslations.'.$locale] = __('menu.translations.name', ['language' => $language]);
        }

        return $attributes;
    }
}
