<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

final class MenuScheduleRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function menuSchedule(): array
    {
        return [
            'scheduleDayOfWeek' => ['required', 'numeric', 'integer', 'min:1', 'max:7'],
            'scheduleStartsAt' => ['required', 'date_format:H:i'],
            'scheduleEndsAt' => ['required', 'date_format:H:i'],
        ];
    }
}
