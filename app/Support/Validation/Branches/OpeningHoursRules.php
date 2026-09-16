<?php

declare(strict_types=1);

namespace App\Support\Validation\Branches;

use App\Support\Validation\NonOverlappingOpeningHours;
use Illuminate\Validation\Rule;

final class OpeningHoursRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function openingHours(bool $isConfigured = false): array
    {
        return [
            'openingHoursConfigured' => ['boolean'],
            'openingHours' => ['array', 'list', 'size:7', Rule::when($isConfigured, [new NonOverlappingOpeningHours])],
            'openingHours.*' => ['array:day_of_week,label,is_closed,intervals'],
            'openingHours.*.day_of_week' => ['required', 'numeric', 'integer', 'min:1', 'max:7', 'distinct'],
            'openingHours.*.label' => ['required', 'string', 'max:40'],
            'openingHours.*.is_closed' => ['boolean'],
            'openingHours.*.intervals' => ['array', 'list', 'max:4'],
            'openingHours.*.intervals.*' => ['array:opens_at,closes_at'],
            'openingHours.*.intervals.*.opens_at' => ['nullable', 'date_format:H:i'],
            'openingHours.*.intervals.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }
}
