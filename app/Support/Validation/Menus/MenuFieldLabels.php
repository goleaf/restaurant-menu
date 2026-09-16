<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Enums\SupportedLocale;

final class MenuFieldLabels
{
    /**
     * @param  'menu'|'category'|'item'|'schedule'  $editor
     * @return array<string, string>
     */
    public static function forEditor(string $editor): array
    {
        $fields = match ($editor) {
            'menu' => ['menuName' => 'validation.attributes.menu_name', 'menuStatus' => 'validation.attributes.menu_status', 'menuSortOrder' => 'validation.attributes.sort_order'],
            'category' => ['categoryMenuId' => 'menu.guest.title', 'categoryParentId' => 'reports.csv.parent_category', 'categoryName' => 'validation.attributes.category_name', 'categoryDescription' => 'menu.translations.description', 'categoryIcon' => 'validation.attributes.icon', 'categorySortOrder' => 'validation.attributes.sort_order', 'categoryIsActive' => 'validation.attributes.is_active'],
            'item' => ['itemMenuId' => 'menu.guest.title', 'itemCategoryId' => 'ui.organizations.brands.branches.menu.index.category', 'itemKitchenDepartmentId' => 'reports.csv.kitchen_department', 'itemName' => 'validation.attributes.item_name', 'itemDescription' => 'menu.translations.description', 'itemPrice' => 'validation.attributes.item_price', 'itemWeight' => 'validation.attributes.item_weight', 'itemVolume' => 'validation.attributes.item_volume', 'itemCalories' => 'validation.attributes.item_calories', 'itemAllergens' => 'menu.allergens.title', 'itemAllergens.*' => 'validation.attributes.allergen', 'itemDietaryLabels' => 'menu.dietary_labels.title', 'itemDietaryLabels.*' => 'validation.attributes.tag', 'itemSortOrder' => 'validation.attributes.sort_order', 'itemIsAvailable' => 'menu.guest.available', 'itemHiddenUntil' => 'menu.admin.hidden_until'],
            'schedule' => ['scheduleMenuId' => 'menu.guest.title', 'scheduleDayOfWeek' => 'ui.organizations.brands.branches.menu.index.day', 'scheduleStartsAt' => 'ui.organizations.brands.branches.menu.index.start', 'scheduleEndsAt' => 'ui.organizations.brands.branches.menu.index.end'],
        };
        $attributes = array_map(static fn (string $key): string => __($key, ['language' => SupportedLocale::English->label()]), $fields);

        if ($editor === 'schedule') {
            return $attributes;
        }

        $translationField = $editor.'Translations';
        $attributes[$translationField] = __('menu.translations.heading');

        foreach (SupportedLocale::labels() as $locale => $language) {
            $name = __('menu.translations.name', ['language' => $language]);
            $attributes[$translationField.'.'.$locale] = $name;
            $attributes[$translationField.'.'.$locale.'.name'] = $name;
            $attributes[$translationField.'.'.$locale.'.description'] = __('menu.translations.description', ['language' => $language]);
        }

        return $attributes;
    }
}
