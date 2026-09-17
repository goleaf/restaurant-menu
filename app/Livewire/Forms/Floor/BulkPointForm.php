<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Floor;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Support\Validation\Branches\ServicePointRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

class BulkPointForm extends Form
{
    public mixed $areaNodeId = '';
    public mixed $bulkType = 'table';
    public mixed $bulkPrefix = 'T';
    public mixed $bulkFrom = 1;
    public mixed $bulkTo = 5;
    public mixed $bulkCapacity = 2;

    /** @return array{area_node_id:?int,type:string,prefix:string,from:int,to:int,capacity:int,icon:string,is_active:bool} */
    public function payload(Branch $branch): array
    {
        if (is_string($this->bulkPrefix)) { $this->bulkPrefix = trim($this->bulkPrefix); }
        $data = $this->validate(['areaNodeId' => ['bail', 'nullable', 'numeric', 'integer', Rule::exists(AreaNode::class, 'id')->where('branch_id', $branch->id)->whereNull('deleted_at')],
            ...ServicePointRules::bulkServicePoint()], attributes: ['areaNodeId' => __('floor.fields.area'), 'bulkType' => __('floor.fields.type'), 'bulkPrefix' => __('floor.fields.prefix'),
            'bulkFrom' => __('floor.fields.from'), 'bulkTo' => __('floor.fields.to'), 'bulkCapacity' => __('floor.fields.capacity')]);
        return ['area_node_id' => $data['areaNodeId'] === '' || $data['areaNodeId'] === null ? null : (int) $data['areaNodeId'], 'type' => $data['bulkType'],
            'prefix' => $data['bulkPrefix'], 'from' => (int) $data['bulkFrom'], 'to' => (int) $data['bulkTo'], 'capacity' => (int) $data['bulkCapacity'], 'icon' => 'squares-2x2', 'is_active' => true];
    }
}
