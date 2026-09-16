<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Support\Validation\Common\MoneyRules;
use App\Support\Validation\Common\RuleFields;
use Illuminate\Validation\Rule;

final class MenuItemRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function menuItem(string $prefix = '', bool $canChangePrices = true, bool $canChangeAvailability = true): array
    {
        $rules = [
            RuleFields::name($prefix, 'itemName') => ['bail', 'required', 'string', 'max:180'],
            RuleFields::name($prefix, 'itemDescription') => ['nullable', 'string', 'max:1200'],
            RuleFields::name($prefix, 'itemWeight') => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            RuleFields::name($prefix, 'itemVolume') => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            RuleFields::name($prefix, 'itemCalories') => ['nullable', 'numeric', 'integer', 'min:0', 'max:999999'],
            RuleFields::name($prefix, 'itemAllergens') => ['array', 'max:'.count(MenuAllergen::cases())],
            RuleFields::name($prefix, 'itemAllergens').'.*' => ['string', 'distinct', Rule::in(MenuAllergen::values())],
            RuleFields::name($prefix, 'itemDietaryLabels') => ['array', 'max:'.count(MenuDietaryLabel::cases())],
            RuleFields::name($prefix, 'itemDietaryLabels').'.*' => ['string', 'distinct', Rule::in(MenuDietaryLabel::values())],
            RuleFields::name($prefix, 'itemSortOrder') => ['required', 'numeric', 'integer', 'min:0', 'max:9999'],
        ];

        if ($canChangePrices) {
            $rules[RuleFields::name($prefix, 'itemPrice')] = MoneyRules::amount();
        }

        if ($canChangeAvailability) {
            $rules[RuleFields::name($prefix, 'itemIsAvailable')] = ['boolean'];
            $rules[RuleFields::name($prefix, 'itemHiddenUntil')] = ['nullable', 'date'];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function onboardingStarterMenu(): array
    {
        return [
            'menuName' => ['bail', 'required', 'string', 'max:160'],
            'categoryName' => ['bail', 'required', 'string', 'max:160'],
            'itemName' => ['bail', 'required', 'string', 'max:180'],
            'itemPrice' => ['bail', ...MoneyRules::amount()],
        ];
    }
}
