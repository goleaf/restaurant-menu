<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Floor;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Support\Floor\FloorOptions;
use App\Support\Validation\Branches\AreaRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AreaForm extends Form
{
    public mixed $parentId = '';

    public mixed $type = 'hall';

    public mixed $icon = 'home';

    public mixed $name = '';

    public mixed $sortOrder = 0;

    public mixed $isActive = true;

    public function loadArea(AreaNode $area): void
    {
        $this->fill(['parentId' => $area->parent_id === null ? '' : (string) $area->parent_id,
            'type' => $area->type->value, 'icon' => $area->icon ?? 'folder', 'name' => $area->name,
            'sortOrder' => $area->sort_order, 'isActive' => $area->is_active]);
    }

    /** @return array{parent_id:?int,type:string,icon:string,name:string,sort_order:int,is_active:bool} */
    public function payload(Branch $branch): array
    {
        if (is_string($this->name)) {
            $this->name = trim($this->name);
        }
        $data = $this->validate([
            'parentId' => ['bail', 'nullable', 'numeric', 'integer', Rule::exists(AreaNode::class, 'id')->where('branch_id', $branch->id)->whereNull('deleted_at')],
            ...AreaRules::areaNode(iconValues: FloorOptions::icons()),
        ], attributes: ['parentId' => __('floor.fields.parent'), 'name' => __('floor.fields.name'), 'type' => __('floor.fields.type'),
            'icon' => __('floor.fields.icon'), 'sortOrder' => __('floor.fields.order'), 'isActive' => __('floor.fields.active')]);

        return ['parent_id' => $data['parentId'] === '' || $data['parentId'] === null ? null : (int) $data['parentId'],
            'type' => $data['type'], 'icon' => $data['icon'], 'name' => $data['name'], 'sort_order' => (int) $data['sortOrder'], 'is_active' => (bool) $data['isActive']];
    }
}
