<?php

declare(strict_types=1);

namespace App\Data\Reports;

use App\Support\Reports\CalendarDateRange;
use Carbon\CarbonImmutable;

final readonly class ReportPeriodInput
{
    public function __construct(public ?string $dateFrom, public ?string $dateTo) {}

    public function resolve(string $timezone, CarbonImmutable $now): CalendarDateRange
    {
        $offset = CalendarDateRange::MAX_DAYS - 1;
        $from = $this->dateFrom === null ? null : CalendarDateRange::date($this->dateFrom, 'date_from');
        $to = $this->dateTo === null ? null : CalendarDateRange::date($this->dateTo, 'date_to');
        $today = CalendarDateRange::date($now->setTimezone($timezone)->toDateString(), 'date_to');
        $start = $from ?? ($to ?? $today)->subDays($offset);
        $end = $to ?? ($from?->addDays($offset) ?? $today);

        return new CalendarDateRange($start->toDateString(), $end->toDateString(), $timezone);
    }
}
