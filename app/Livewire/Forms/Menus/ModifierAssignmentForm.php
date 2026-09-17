<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class ModifierAssignmentForm extends Form
{
    public mixed $modifierItemMenuId = '';

    public mixed $modifierItemId = '';

    public mixed $modifierItemGroupId = '';

    /** @return array<string,mixed> */
    public function validated(Branch $branch): array
    {
        $menuId = is_string($this->modifierItemMenuId) || is_int($this->modifierItemMenuId) ? (int) $this->modifierItemMenuId : 0;
        $rules = [
            'modifierItemMenuId' => ['bail', 'required', 'numeric', 'integer', Rule::exists((new Menu)->getTable(), 'id')->where('branch_id', $branch->id)],
            'modifierItemId' => ['bail', 'required', 'numeric', 'integer', Rule::exists((new MenuItem)->getTable(), 'id')->where('menu_id', $menuId)],
            'modifierItemGroupId' => ['bail', 'required', 'numeric', 'integer', Rule::exists((new ModifierGroup)->getTable(), 'id')->where('branch_id', $branch->id)],
        ];

        return $this->validate($rules);
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return ['modifierItemMenuId' => __('menu.guest.title'), 'modifierItemId' => __('ui.actions.analytics.buildbasicanalyticsdashboardaction.dish'), 'modifierItemGroupId' => __('ui.organizations.brands.branches.menu.index.modifier_group')];
    }
}
