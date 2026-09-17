<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Support\Validation\Menus\MenuTranslationRules;
use App\Support\Validation\Menus\ModifierRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class ModifierOptionForm extends Form
{
    public mixed $modifierOptionGroupId = '';

    public mixed $modifierOptionName = '';

    public mixed $modifierOptionPriceDelta = '0.00';

    public mixed $modifierOptionIsAvailable = true;

    public mixed $modifierOptionSortOrder = 0;

    public mixed $modifierOptionTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    /** @return array<string,mixed> */
    public function validated(Branch $branch, bool $canChangePrices, bool $canChangeAvailability, ?ModifierOption $option = null, ?int $ignoreId = null): array
    {
        $this->modifierOptionName = is_string($this->modifierOptionName) ? trim($this->modifierOptionName) : $this->modifierOptionName;
        $rules = [...ModifierRules::modifierOption(canChangePrices: $canChangePrices, canChangeAvailability: $canChangeAvailability),
            ...MenuTranslationRules::translatedNames('modifierOptionTranslations')];
        $groupId = $option->modifier_group_id ?? (is_string($this->modifierOptionGroupId) || is_int($this->modifierOptionGroupId) ? (int) $this->modifierOptionGroupId : 0);
        if ($option === null) {
            $rules['modifierOptionGroupId'] = ['bail', 'required', 'numeric', 'integer', Rule::exists((new ModifierGroup)->getTable(), 'id')->where('branch_id', $branch->id)];
        }
        $unique = Rule::unique((new ModifierOption)->getTable(), 'name')->where('modifier_group_id', $groupId);
        $ignoreId = $option->id ?? $ignoreId;
        $rules['modifierOptionName'][] = $ignoreId === null ? $unique : $unique->ignore($ignoreId);

        return $this->validate($rules);
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return ModifierRules::optionValidationAttributes();
    }
}
