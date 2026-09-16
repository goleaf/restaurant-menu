<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use App\Data\Menus\MenuItemData;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Support\MoneyFormatter;
use App\Support\Validation\Menus\MenuItemRules;
use App\Support\Validation\Menus\MenuTranslationRules;
use App\Support\Validation\Menus\MenuFieldLabels;
use App\Support\Validation\Menus\MenuScopeRules;
use Livewire\Form;

final class MenuItemForm extends Form
{
    public mixed $itemMenuId = '';

    public mixed $itemCategoryId = '';

    public mixed $itemKitchenDepartmentId = '';

    public mixed $itemName = '';

    public mixed $itemDescription = '';

    public mixed $itemPrice = '0.00';

    public mixed $itemWeight = '';

    public mixed $itemVolume = '';

    public mixed $itemCalories = '';

    public mixed $itemAllergens = [];

    public mixed $itemDietaryLabels = [];

    public mixed $itemSortOrder = 0;

    public mixed $itemIsAvailable = true;

    public mixed $itemHiddenUntil = '';

    public mixed $itemTranslations = [
        'en' => ['name' => '', 'description' => ''],
        'lt' => ['name' => '', 'description' => ''],
        'ru' => ['name' => '', 'description' => ''],
    ];

    /** @return array{menuId: int, categoryId: int, kitchenDepartmentId: ?int, data: MenuItemData} */
    public function validated(Branch $branch, bool $canChangePrices, bool $canChangeAvailability, ?MenuItem $item = null): array
    {
        $this->itemName = is_string($this->itemName) ? trim($this->itemName) : $this->itemName;
        $this->itemDescription = is_string($this->itemDescription) ? trim($this->itemDescription) : $this->itemDescription;
        $rules = [
            'itemMenuId' => ['bail', 'required', 'numeric', 'integer', MenuScopeRules::menu($branch)],
            'itemCategoryId' => ['bail', 'required', 'numeric', 'integer', MenuScopeRules::category($this->itemMenuId)],
            'itemKitchenDepartmentId' => ['bail', 'nullable', 'numeric', 'integer', MenuScopeRules::department($branch)],
            ...MenuItemRules::menuItem(canChangePrices: $canChangePrices, canChangeAvailability: $canChangeAvailability),
            ...MenuTranslationRules::menuTranslations('itemTranslations', 180, 1200),
        ];
        $unique = MenuScopeRules::itemName($this->itemCategoryId, $item);
        if ($unique !== null) {
            $rules['itemName'][] = $unique;
        }
        $values = $this->validate($rules);
        $data = [
            'name' => $values['itemName'], 'description' => self::optionalString($values['itemDescription'] ?? null),
            'weight' => self::optionalString($values['itemWeight'] ?? null), 'volume' => self::optionalString($values['itemVolume'] ?? null),
            'calories' => ($values['itemCalories'] ?? '') === '' || $values['itemCalories'] === null ? null : (int) $values['itemCalories'],
            'allergens' => array_values($values['itemAllergens'] ?? []), 'dietary_labels' => array_values($values['itemDietaryLabels'] ?? []),
            'sort_order' => (int) $values['itemSortOrder'], 'translations' => $values['itemTranslations'],
        ];
        if ($canChangePrices) {
            $data['price'] = $values['itemPrice'];
        }
        if ($canChangeAvailability) {
            $data['is_available'] = (bool) $values['itemIsAvailable'];
            $data['hidden_until'] = self::optionalString($values['itemHiddenUntil'] ?? null);
        }

        return ['menuId' => (int) $values['itemMenuId'], 'categoryId' => (int) $values['itemCategoryId'], 'kitchenDepartmentId' => MenuScopeRules::identifier($values['itemKitchenDepartmentId'] ?? null), 'data' => MenuItemData::fromValidated($data)];
    }

    /** @param array<string, array{name: string, description: string}> $translations */
    public function populate(MenuItem $item, array $translations, string $timezone): void
    {
        $this->itemMenuId = (string) $item->menu_id;
        $this->itemCategoryId = (string) $item->category_id;
        $this->itemKitchenDepartmentId = $item->kitchen_department_id === null ? '' : (string) $item->kitchen_department_id;
        $this->itemName = $item->name;
        $this->itemDescription = $item->description ?? '';
        $this->itemPrice = MoneyFormatter::centsToDecimal($item->price_cents);
        $this->itemWeight = $item->weight ?? '';
        $this->itemVolume = $item->volume ?? '';
        $this->itemCalories = $item->calories === null ? '' : (string) $item->calories;
        $this->itemAllergens = $item->allergens;
        $this->itemDietaryLabels = $item->dietary_labels;
        $this->itemSortOrder = $item->sort_order;
        $this->itemIsAvailable = $item->is_available;
        $this->itemHiddenUntil = $item->hidden_until?->setTimezone($timezone)->format('Y-m-d\\TH:i') ?? '';
        $this->itemTranslations = $translations;
    }

    public function clearForMenu(string $menuId, string $categoryId, string $departmentId): void
    {
        $this->reset();
        $this->itemMenuId = $menuId;
        $this->itemCategoryId = $categoryId;
        $this->itemKitchenDepartmentId = $departmentId;
    }

    private static function optionalString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return MenuFieldLabels::forEditor('item');
    }
}
