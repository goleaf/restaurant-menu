<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Availability;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Support\Validation\Availability\ScheduleRules;
use App\Support\Validation\Branches\OpeningHoursRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

final class WeeklyScheduleForm extends Form
{
    public mixed $mode = 'unrestricted';

    public mixed $openingHoursConfigured = false;

    public mixed $openingHours = [];

    public mixed $copyFrom = 0;

    public mixed $copyTo = [];

    /** @param list<array<string,mixed>> $days */
    public function populate(array $days, string $mode): void
    {
        $this->openingHours = $days;
        $this->mode = $mode;
    }

    /** @return array{mode:string,days:list<array<string,mixed>>} */
    public function validatedDraft(): array
    {
        $this->openingHoursConfigured = $this->mode === 'weekly';
        $data = $this->validate([
            'mode' => ['required', 'string', Rule::in(['unrestricted', 'weekly', 'closed'])],
            ...OpeningHoursRules::openingHours($this->openingHoursConfigured),
            'openingHours.*.is_closed' => ['required', 'boolean'],
            'openingHours.*.intervals' => ['present', 'array', 'list', 'max:4'],
            'openingHours.*.intervals.*.opens_at' => ['present', 'nullable', 'date_format:H:i'],
            'openingHours.*.intervals.*.closes_at' => ['present', 'nullable', 'date_format:H:i'],
        ]);

        return ['mode' => $data['mode'], 'days' => $data['openingHours']];
    }

    /** @return array{mode:string,days:list<array<string,mixed>>,intervals:list<array<string,mixed>>} */
    public function validatedOperation(): array
    {
        $draft = $this->validatedDraft();
        $days = [];
        $intervals = [];
        foreach ($draft['days'] as $day) {
            $closed = $draft['mode'] === 'closed' || in_array($day['is_closed'], [true, 1, '1'], true);
            $rows = $closed ? [] : $day['intervals'];
            $days[] = ['day_of_week' => (int) $day['day_of_week'], 'is_closed' => $closed, 'intervals' => $rows];
            if ($draft['mode'] === 'weekly') {
                foreach ($rows as $row) {
                    $intervals[] = ['day_of_week' => (int) $day['day_of_week'], 'starts_at' => $row['opens_at'], 'ends_at' => $row['closes_at']];
                }
            }
        }
        try {
            $days = ScheduleRules::branch($days, $draft['mode'] !== 'unrestricted');
            $intervals = ScheduleRules::menu($intervals);
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'openingHours') ? 'weekly.'.$field : 'weekly.mode'] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }

        return ['mode' => $draft['mode'], 'days' => $days, 'intervals' => $intervals];
    }

    /** @return array{from:int,to:list<int>,rows:list<array<string,mixed>>} */
    public function validatedCopy(): array
    {
        $data = $this->validate([
            'copyFrom' => ['required', 'numeric', 'integer', 'between:0,6'],
            'copyTo' => ['required', 'array', 'list', 'min:1', 'max:6'],
            'copyTo.*' => ['required', 'numeric', 'integer', 'between:0,6', 'distinct', Rule::notIn([$this->copyFrom])],
        ]);
        $this->validatedDraft();
        $from = (int) $data['copyFrom'];
        $to = array_map(intval(...), $data['copyTo']);
        $rows = [];
        foreach ($to as $index) {
            $rows[] = ['label' => $this->openingHours[$index]['label'], 'before' => $this->openingHours[$index], 'after' => [
                ...$this->openingHours[$index], 'is_closed' => $this->openingHours[$from]['is_closed'], 'intervals' => $this->openingHours[$from]['intervals'],
            ]];
        }

        return ['from' => $from, 'to' => $to, 'rows' => $rows];
    }

    /** @param array{from:int,to:list<int>,rows:list<array<string,mixed>>} $copy */
    public function copyDays(array $copy): void
    {
        foreach ($copy['to'] as $index) {
            $this->openingHours[$index]['is_closed'] = $this->openingHours[$copy['from']]['is_closed'];
            $this->openingHours[$index]['intervals'] = $this->openingHours[$copy['from']]['intervals'];
        }
        $this->mode = 'weekly';
    }

    public function addInterval(int $dayIndex): void
    {
        if (! is_array($this->openingHours) || ! isset($this->openingHours[$dayIndex]) || ! is_array($this->openingHours[$dayIndex])) {
            return;
        }
        $intervals = $this->openingHours[$dayIndex]['intervals'] ?? [];
        if (! is_array($intervals) || count($intervals) >= 4) {
            return;
        }
        $this->openingHours[$dayIndex]['is_closed'] = false;
        $this->openingHours[$dayIndex]['intervals'][] = ['opens_at' => '10:00', 'closes_at' => '22:00'];
        $this->mode = 'weekly';
    }

    public function removeInterval(int $dayIndex, int $intervalIndex): void
    {
        if (! is_array($this->openingHours) || ! is_array($this->openingHours[$dayIndex]['intervals'] ?? null)) {
            return;
        }
        unset($this->openingHours[$dayIndex]['intervals'][$intervalIndex]);
        $this->openingHours[$dayIndex]['intervals'] = array_values($this->openingHours[$dayIndex]['intervals']);
        $this->openingHours[$dayIndex]['is_closed'] = $this->openingHours[$dayIndex]['intervals'] === [];
    }

    /** @return list<array{label:string,index:int,intervals:list<mixed>,can_add:bool}> */
    public function displayDays(): array
    {
        $result = [];
        foreach (GetBranchOpeningStatusAction::dayLabels() as $number => $label) {
            $intervals = is_array($this->openingHours) && is_array($this->openingHours[$number - 1]['intervals'] ?? null) ? $this->openingHours[$number - 1]['intervals'] : [];
            $result[] = ['label' => $label, 'index' => $number - 1, 'intervals' => array_slice($intervals, 0, 4), 'can_add' => count($intervals) < 4];
        }

        return $result;
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return [
            'mode' => __('availability.schedule_mode'),
            'openingHours' => __('availability.branch_schedule'),
            'openingHours.*.day_of_week' => __('availability.day'),
            'openingHours.*.label' => __('availability.day'),
            'openingHours.*.is_closed' => __('availability.closed_day'),
            'openingHours.*.intervals' => __('availability.intervals'),
            'openingHours.*.intervals.*.opens_at' => __('availability.opens'),
            'openingHours.*.intervals.*.closes_at' => __('availability.closes'),
            'copyFrom' => __('availability.copy_from'),
            'copyTo' => __('availability.copy_to'),
            'copyTo.*' => __('availability.day'),
        ];
    }
}
