<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Floor;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Support\Floor\FloorOptions;
use App\Support\Validation\Branches\ServicePointRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PointForm extends Form
{
    public mixed $areaNodeId = '';

    public mixed $type = 'table';

    public mixed $icon = 'squares-2x2';

    public mixed $name = '';

    public mixed $displayNumber = '';

    public mixed $capacity = 2;

    public mixed $isActive = true;

    public function loadPoint(ServicePoint $point): void
    {
        $this->fill(['areaNodeId' => $point->area_node_id === null ? '' : (string) $point->area_node_id,
            'type' => $point->type->value, 'icon' => $point->icon ?? 'squares-2x2', 'name' => $point->name,
            'displayNumber' => $point->display_number ?? '', 'capacity' => $point->capacity, 'isActive' => $point->is_active]);
    }

    /** @return array{area_node_id:?int,type:string,icon:string,name:string,display_number:?string,capacity:int,is_active:bool} */
    public function payload(Branch $branch): array
    {
        foreach (['name', 'displayNumber'] as $field) {
            if (is_string($this->{$field})) {
                $this->{$field} = trim($this->{$field});
            }
        }
        $data = $this->validate([
            'areaNodeId' => ['bail', 'nullable', 'numeric', 'integer', Rule::exists(AreaNode::class, 'id')->where('branch_id', $branch->id)->whereNull('deleted_at')],
            ...ServicePointRules::servicePoint(iconValues: FloorOptions::icons()),
        ], attributes: $this->validationAttributes());

        return ['area_node_id' => $data['areaNodeId'] === '' || $data['areaNodeId'] === null ? null : (int) $data['areaNodeId'],
            'type' => $data['type'], 'icon' => $data['icon'], 'name' => $data['name'], 'display_number' => $data['displayNumber'] === '' ? null : $data['displayNumber'],
            'capacity' => (int) $data['capacity'], 'is_active' => (bool) $data['isActive']];
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return collect(['areaNodeId' => 'floor.fields.area', 'type' => 'floor.fields.type', 'icon' => 'floor.fields.icon', 'name' => 'floor.fields.name', 'displayNumber' => 'floor.fields.number', 'capacity' => 'floor.fields.capacity', 'isActive' => 'floor.fields.active'])
            ->map(fn (string $key): string => __($key))->all();
    }
}
