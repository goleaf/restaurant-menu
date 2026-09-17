<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Availability;

use App\Support\Validation\Availability\ScheduleRules;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

final class ExceptionsForm extends Form
{
    public mixed $exceptions = [];

    /** @return list<array<string,mixed>> */
    public function validatedDraft(): array
    {
        $data = $this->validate([
            'exceptions' => ['array', 'list', 'max:366'],
            'exceptions.*' => ['array:local_date,is_closed,intervals'],
            'exceptions.*.local_date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'exceptions.*.is_closed' => ['required', 'boolean'],
            'exceptions.*.intervals' => ['array', 'list', 'max:4'],
            'exceptions.*.intervals.*' => ['array:opens_at,closes_at'],
            'exceptions.*.intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'exceptions.*.intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ])['exceptions'];
        try {
            return ScheduleRules::exceptions($data);
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $field => $errors) {
                $messages['exceptions.'.$field] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }
    }

    /** @return list<array{index:int,intervals:list<mixed>,can_add:bool}> */
    public function displayRows(): array
    {
        $rows = is_array($this->exceptions) ? array_slice($this->exceptions, 0, 366) : [];
        $result = [];
        foreach ($rows as $index => $row) {
            $intervals = is_array($row) && is_array($row['intervals'] ?? null) ? array_slice($row['intervals'], 0, 4) : [];
            $result[] = ['index' => $index, 'intervals' => $intervals, 'can_add' => count($intervals) < 4];
        }

        return $result;
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return [
            'exceptions' => __('availability.exceptions_title'),
            'exceptions.*.local_date' => __('availability.exception_date'),
            'exceptions.*.is_closed' => __('availability.closed_day'),
            'exceptions.*.intervals' => __('availability.intervals'),
            'exceptions.*.intervals.*.opens_at' => __('availability.opens'),
            'exceptions.*.intervals.*.closes_at' => __('availability.closes'),
        ];
    }
}
