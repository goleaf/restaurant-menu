<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Staff;

use Livewire\Form;

class WaiterAssignmentForm extends Form
{
    public mixed $areaIds = [];

    public mixed $search = '';

    /** @return list<int> */
    public function validatedIds(): array
    {
        $values = $this->validate([
            'areaIds' => ['array', 'max:500'],
            'areaIds.*' => ['required', 'numeric', 'integer', 'min:1', 'distinct'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        return array_map(static fn (mixed $id): int => (int) $id, $values['areaIds']);
    }
}
