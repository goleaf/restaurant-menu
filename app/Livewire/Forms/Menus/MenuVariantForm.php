<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use App\Models\MenuItemVariant;
use App\Support\Validation\Menus\MenuVariantRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class MenuVariantForm extends Form
{
    public mixed $variantType = 'portion';

    public mixed $variantName = '';

    public mixed $variantPrice = '0.00';

    public mixed $variantWeight = null;

    public mixed $variantVolume = null;

    public mixed $variantIsDefault = false;

    public mixed $variantIsAvailable = true;

    public mixed $variantSortOrder = 0;

    public mixed $variantTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    /** @return array<string,mixed> */
    public function validated(int $itemId, bool $canChangePrices, bool $canChangeAvailability, ?int $ignoreId = null): array
    {
        $rules = MenuVariantRules::menuItemVariant(canChangePrices: $canChangePrices, canChangeAvailability: $canChangeAvailability);
        $type = is_string($this->variantType) ? $this->variantType : '';
        $unique = Rule::unique((new MenuItemVariant)->getTable(), 'name')->where(fn ($query) => $query->where('menu_item_id', $itemId)->where('type', $type));
        $rules['variantName'][] = $ignoreId === null ? $unique : $unique->ignore($ignoreId);

        return $this->validate($rules);
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return MenuVariantRules::validationAttributes();
    }
}
