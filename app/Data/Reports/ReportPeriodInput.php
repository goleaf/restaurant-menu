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
        $from = $this->dateFrom;
        $to = $this->dateTo;

        if ($from === null) {
            $end = CalendarDateRange::date($to ?? $now->setTimezone($timezone)->toDateString(), 'date_to');
            $from = $end->subDays($offset)->toDateString();
            $to ??= $end->toDateString();
        } elseif ($to === null) {
            $to = CalendarDateRange::date($from, 'date_from')->addDays($offset)->toDateString();
        }

        return new CalendarDateRange($from, $to, $timezone);
    }
}
