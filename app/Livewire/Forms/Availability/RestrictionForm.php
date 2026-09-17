<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Availability;

use Illuminate\Validation\Rule;
use Livewire\Form;

final class RestrictionForm extends Form
{
    public mixed $operation = 'stop';

    public mixed $untilDate = '';

    public mixed $untilTime = '';

    public mixed $reason = '';

    /** @return array{operation:string,until:?string,reason:?string} */
    public function validatedDraft(): array
    {
        $data = $this->validate([
            'operation' => ['required', 'string', Rule::in(['stop', 'resume', 'hide', 'unhide'])],
            'untilDate' => [Rule::requiredIf($this->operation === 'hide'), 'nullable', 'date_format:Y-m-d'],
            'untilTime' => [Rule::requiredIf($this->operation === 'hide'), 'nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return ['operation' => $data['operation'], 'until' => $data['operation'] === 'hide' ? $data['untilDate'].'T'.$data['untilTime'] : null, 'reason' => $data['reason'] ?: null];
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return [
            'operation' => __('availability.operation'),
            'untilDate' => __('availability.until_date'),
            'untilTime' => __('availability.until_time'),
            'reason' => __('availability.reason'),
        ];
    }
}
