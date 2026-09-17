<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Support\PlainText;
use App\Support\Validation\Menus\MenuTranslationRules;
use App\Support\Validation\Menus\MenuVariantRules;
use App\Support\Validation\Menus\ModifierRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ValidateDishConfigurationInputAction
{
    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function variant(MenuItem $item, array $data, ?MenuItemVariant $existing = null): array
    {
        $map = ['type' => 'variantType', 'name' => 'variantName', 'price' => 'variantPrice', 'weight' => 'variantWeight',
            'volume' => 'variantVolume', 'is_default' => 'variantIsDefault', 'is_available' => 'variantIsAvailable',
            'sort_order' => 'variantSortOrder', 'translations' => 'variantTranslations'];
        $rules = MenuVariantRules::menuItemVariant(canChangePrices: array_key_exists('price', $data), canChangeAvailability: array_key_exists('is_available', $data));
        $rules['variantIsDefault'] = ['required', 'boolean'];
        $this->validate($data, $map, $rules, 'variantTranslations');
        $data['name'] = PlainText::required($data['name'], 160, squish: true);
        $this->unique(MenuItemVariant::query()->where('menu_item_id', $item->id)->where('type', $data['type'])
            ->where('name', $data['name'])->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))->exists());
        $data['sort_order'] = (int) $data['sort_order'];
        $data['is_default'] = (bool) $data['is_default'];
        foreach (['weight', 'volume'] as $field) {
            $data[$field] = isset($data[$field]) && $data[$field] !== '' ? (string) $data[$field] : null;
        }

        return $data;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function group(Branch $branch, array $data, ?ModifierGroup $existing = null): array
    {
        $map = ['name' => 'modifierGroupName', 'is_required' => 'modifierGroupIsRequired', 'min_select' => 'modifierGroupMinSelect',
            'max_select' => 'modifierGroupMaxSelect', 'sort_order' => 'modifierGroupSortOrder', 'translations' => 'modifierGroupTranslations'];
        $rules = [...ModifierRules::modifierGroup(), ...MenuTranslationRules::translatedNames('modifierGroupTranslations')];
        $rules['modifierGroupIsRequired'] = ['required', 'boolean'];
        $this->validate($data, $map, $rules, 'modifierGroupTranslations');
        $data['name'] = PlainText::required($data['name'], 160, squish: true);
        $this->unique(ModifierGroup::query()->where('branch_id', $branch->id)->where('name', $data['name'])
            ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))->exists());
        $data['is_required'] = (bool) $data['is_required'];
        foreach (['min_select', 'max_select', 'sort_order'] as $field) {
            $data[$field] = (int) $data[$field];
        }

        return $data;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function option(ModifierGroup $group, array $data, ?ModifierOption $existing = null): array
    {
        $map = ['name' => 'modifierOptionName', 'price_delta' => 'modifierOptionPriceDelta', 'is_available' => 'modifierOptionIsAvailable',
            'sort_order' => 'modifierOptionSortOrder', 'translations' => 'modifierOptionTranslations'];
        $rules = [...ModifierRules::modifierOption(canChangePrices: array_key_exists('price_delta', $data), canChangeAvailability: array_key_exists('is_available', $data)),
            ...MenuTranslationRules::translatedNames('modifierOptionTranslations')];
        $this->validate($data, $map, $rules, 'modifierOptionTranslations');
        $data['name'] = PlainText::required($data['name'], 160, squish: true);
        $this->unique(ModifierOption::query()->where('modifier_group_id', $group->id)->where('name', $data['name'])
            ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))->exists());
        $data['sort_order'] = (int) $data['sort_order'];

        return $data;
    }

    /** @param array<string,mixed> $data @param array<string,string> $map @param array<string,list<mixed>> $rules */
    private function validate(array $data, array $map, array $rules, string $translations): void
    {
        $values = [];
        foreach ($map as $field => $formField) {
            if (array_key_exists($field, $data)) {
                $values[$formField] = $data[$field];
            }
        }
        if (! array_key_exists('translations', $data)) {
            $rules = array_filter($rules, fn ($key): bool => ! str_starts_with($key, $translations), ARRAY_FILTER_USE_KEY);
        }
        $attributes = match ($translations) {
            'variantTranslations' => MenuVariantRules::validationAttributes(),
            'modifierGroupTranslations' => ModifierRules::groupValidationAttributes(),
            default => ModifierRules::optionValidationAttributes(),
        };
        Validator::make($values, $rules, attributes: $attributes)->validate();
    }

    private function unique(bool $exists): void
    {
        if ($exists) {
            throw ValidationException::withMessages(['configuration' => __('dish.errors.configuration_name_exists')]);
        }
    }
}
