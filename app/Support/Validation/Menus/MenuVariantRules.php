<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Enums\MenuItemVariantType;
use App\Support\Validation\Common\MoneyRules;
use App\Support\Validation\Common\RuleFields;
use Illuminate\Validation\Rule;

final class MenuVariantRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function menuItemVariant(
        string $prefix = '',
        bool $canChangePrices = true,
        bool $canChangeAvailability = true,
    ): array {
        $rules = [
            RuleFields::name($prefix, 'variantType') => ['required', 'string', Rule::in(MenuItemVariantType::values())],
            RuleFields::name($prefix, 'variantName') => ['bail', 'required', 'string', 'max:160'],
            RuleFields::name($prefix, 'variantWeight') => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            RuleFields::name($prefix, 'variantVolume') => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            RuleFields::name($prefix, 'variantIsDefault') => ['boolean'],
            RuleFields::name($prefix, 'variantSortOrder') => ['required', 'numeric', 'integer', 'min:0', 'max:9999'],
            ...MenuTranslationRules::translatedNames(RuleFields::name($prefix, 'variantTranslations')),
        ];

        if ($canChangePrices) {
            $rules[RuleFields::name($prefix, 'variantPrice')] = MoneyRules::amount();
        }

        if ($canChangeAvailability) {
            $rules[RuleFields::name($prefix, 'variantIsAvailable')] = ['boolean'];
        }

        return $rules;
    }
}
