<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Departments;

use Illuminate\Validation\Rule;
use Livewire\Form;

final class PreparationSelectionForm extends Form
{
    /** @var list<mixed> */
    public array $itemIds = [];

    public string $status = 'accepted';

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validate([
            'itemIds' => ['required', 'array', 'min:1', 'max:24'],
            'itemIds.*' => ['required', 'numeric', 'integer', 'min:1', 'distinct'],
            'status' => ['required', 'string', Rule::in(['accepted', 'in_progress', 'ready'])],
        ], attributes: ['itemIds' => __('preparation.selection.rows'), 'itemIds.*' => __('preparation.selection.rows'), 'status' => __('ui.departments.dashboard.filter')]);
    }
}
