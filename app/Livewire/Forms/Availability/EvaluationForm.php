<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Availability;

use App\Support\Validation\Availability\BranchLocalDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class EvaluationForm extends Form
{
    public mixed $mode = 'current';

    public mixed $date = '';

    public mixed $time = '';

    public function validatedInstant(string $timezone): ?string
    {
        $values = $this->validate([
            'mode' => ['required', 'string', Rule::in(['current', 'future'])],
            'date' => [Rule::requiredIf($this->mode === 'future'), 'nullable', 'date_format:Y-m-d'],
            'time' => [Rule::requiredIf($this->mode === 'future'), 'nullable', 'date_format:H:i'],
        ]);
        if ($values['mode'] === 'current') {
            return null;
        }
        $local = $values['date'].'T'.$values['time'];
        Validator::make(['evaluation' => ['date' => $local]], ['evaluation.date' => [new BranchLocalDateTime($timezone, CarbonImmutable::now())]])->validate();

        return BranchLocalDateTime::parse($local, $timezone)->utc()->toIso8601String();
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return [
            'mode' => __('availability.evaluate_mode'),
            'date' => __('availability.exception_date'),
            'time' => __('availability.until_time'),
        ];
    }
}
