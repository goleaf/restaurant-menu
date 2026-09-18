<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Floor;

use App\Models\AreaNode;
use Illuminate\Validation\Rule;
use Livewire\Form;

class MoveForm extends Form
{
    public mixed $targetAreaId = '';

    public function target(int $branchId): ?int
    {
        $data = $this->validate(['targetAreaId' => ['bail', 'nullable', 'numeric', 'integer', Rule::exists(AreaNode::class, 'id')->where('branch_id', $branchId)->whereNull('deleted_at')]], attributes: ['targetAreaId' => __('floor.fields.target')]);

        return $data['targetAreaId'] === '' || $data['targetAreaId'] === null ? null : (int) $data['targetAreaId'];
    }
}
