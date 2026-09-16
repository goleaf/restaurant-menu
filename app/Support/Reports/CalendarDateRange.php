<?php

declare(strict_types=1);

namespace App\Support\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class CalendarDateRange
{
    public const int MAX_DAYS = 31;

    public CarbonImmutable $startedAt;

    public CarbonImmutable $endedAt;

    public function __construct(public string $dateFrom, public string $dateTo, public string $timezone)
    {
        $start = self::date($dateFrom, 'date_from');
        $end = self::date($dateTo, 'date_to');

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['date_to' => __('validation.rules.report_period_order', [
                'attribute' => __('validation.attributes.date_to'),
                'other' => __('validation.attributes.date_from'),
            ])]);
        }

        if ($end->gte($start->addDays(self::MAX_DAYS))) {
            throw ValidationException::withMessages(['date_to' => __('validation.rules.report_period_too_long', ['max' => self::MAX_DAYS])]);
        }

        $this->startedAt = $start->shiftTimezone($timezone)->startOfDay()->utc();
        $this->endedAt = $end->shiftTimezone($timezone)->endOfDay()->utc();
    }

    public static function date(string $value, string $field): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw ValidationException::withMessages([$field => __('reports.errors.date')]);
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

        if (! $date instanceof CarbonImmutable || $date->toDateString() !== $value) {
            throw ValidationException::withMessages([$field => __('reports.errors.date')]);
        }

        return $date;
    }
}
