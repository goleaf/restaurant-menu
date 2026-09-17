<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Support\Validation\Menus\MenuTranslationRules;
use App\Support\Validation\Menus\ModifierRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class ModifierGroupForm extends Form
{
    public mixed $modifierGroupName = '';

    public mixed $modifierGroupIsRequired = false;

    public mixed $modifierGroupMinSelect = 0;

    public mixed $modifierGroupMaxSelect = 1;

    public mixed $modifierGroupSortOrder = 0;

    public mixed $modifierGroupTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    /** @return array<string,mixed> */
    public function validated(Branch $branch, ?int $ignoreId = null): array
    {
        $this->modifierGroupName = is_string($this->modifierGroupName) ? trim($this->modifierGroupName) : $this->modifierGroupName;
        $rules = [...ModifierRules::modifierGroup(), ...MenuTranslationRules::translatedNames('modifierGroupTranslations')];
        $unique = Rule::unique((new ModifierGroup)->getTable(), 'name')->where(fn ($query) => $query->where('branch_id', $branch->id));
        $rules['modifierGroupName'][] = $ignoreId === null ? $unique : $unique->ignore($ignoreId);

        return $this->validate($rules);
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return ModifierRules::groupValidationAttributes();
    }
}
