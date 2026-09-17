<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Availability;

use App\Support\Validation\Availability\BranchLocalDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class PauseForm extends Form
{
    public mixed $mode = 'indefinite';

    public mixed $durationMinutes = 30;

    public mixed $untilDate = '';

    public mixed $untilTime = '';

    public mixed $reason = '';

    /** @return array{closed:bool,reason:?string,until:?string,duration:?int} */
    public function validatedDraft(string $timezone, bool $requireFuture = true): array
    {
        $data = $this->validate([
            'mode' => ['required', 'string', Rule::in(['resume', 'indefinite', 'duration', 'until'])],
            'durationMinutes' => [Rule::requiredIf($this->mode === 'duration'), 'nullable', 'numeric', 'integer', 'min:1', 'max:10080'],
            'untilDate' => [Rule::requiredIf($this->mode === 'until'), 'nullable', 'date_format:Y-m-d'],
            'untilTime' => [Rule::requiredIf($this->mode === 'until'), 'nullable', 'date_format:H:i'],
            'reason' => [Rule::requiredIf($this->mode !== 'resume'), 'nullable', 'string', 'max:255'],
        ]);
        $until = $data['mode'] === 'until' ? $data['untilDate'].'T'.$data['untilTime'] : null;
        if ($until !== null) {
            Validator::make(['pause' => ['untilDate' => $until]], ['pause.untilDate' => [new BranchLocalDateTime($timezone, $requireFuture ? CarbonImmutable::now('UTC') : null)]])->validate();
        }

        return ['closed' => $data['mode'] !== 'resume', 'reason' => $data['reason'] ?: null, 'until' => $until,
            'duration' => $data['mode'] === 'duration' ? (int) $data['durationMinutes'] : null];
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        return [
            'mode' => __('availability.pause_mode'),
            'durationMinutes' => __('availability.duration_minutes'),
            'untilDate' => __('availability.until_date'),
            'untilTime' => __('availability.until_time'),
            'reason' => __('availability.public_reason'),
        ];
    }
}
