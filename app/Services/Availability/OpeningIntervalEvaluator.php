<?php

declare(strict_types=1);

namespace App\Services\Availability;

use Carbon\CarbonImmutable;

/**
 * @phpstan-type Interval array{opens_at: string, closes_at: string}
 * @phpstan-type WeeklyInterval array{day_of_week: int, opens_at: string, closes_at: string}
 * @phpstan-type ExceptionDay array{local_date: string, is_closed: bool, intervals: list<Interval>}
 * @phpstan-type Layer array{weekly: list<WeeklyInterval>, exceptions: list<ExceptionDay>, empty_allows: bool}
 * @phpstan-type Window array{start: CarbonImmutable, end: CarbonImmutable}
 */
final class OpeningIntervalEvaluator
{
    /**
     * Every decision uses the supplied instant. Candidate boundaries come from actual
     * occurrences; an enumeration limit can never become a claimed reopening time.
     *
     * @param  list<Layer>  $layers
     * @return array{allows_now: bool, current_closes_at: CarbonImmutable|null, next_change_at: CarbonImmutable|null, next_orderable_at: CarbonImmutable|null}
     */
    public function evaluate(array $layers, string $timezone, CarbonImmutable $instant, ?CarbonImmutable $pausedUntil = null, bool $indefinitelyPaused = false): array
    {
        $instant = $instant->setTimezone($timezone);
        $dates = $this->candidateDates($layers, $instant, $pausedUntil);
        $windows = [];
        $exceptionMaps = [];
        $events = [];
        foreach ($layers as $index => $layer) {
            $exceptionMaps[$index] = array_column($layer['exceptions'], null, 'local_date');
            foreach ($dates as $date) {
                foreach ($this->windows($layer, $date, $exceptionMaps[$index]) as $window) {
                    foreach ([$window['start'], $window['end']] as $boundary) {
                        if ($boundary->greaterThan($instant)) {
                            $events[$boundary->getTimestamp()] = $boundary;
                        }
                    }
                }
            }
            $windows[$index] = [];
        }
        if ($pausedUntil !== null && $pausedUntil->greaterThan($instant)) {
            $events[$pausedUntil->getTimestamp()] = $pausedUntil->setTimezone($timezone);
        }
        ksort($events);
        $state = function (CarbonImmutable $at) use ($layers, $exceptionMaps, &$windows): array {
            $result = [];
            foreach ($layers as $index => $layer) {
                $date = $at->toDateString();
                $windows[$index][$date] ??= $this->windows($layer, $at->startOfDay(), $exceptionMaps[$index]);
                $result[] = $this->contains($windows[$index][$date], $at);
            }

            return $result;
        };
        $canOrder = static fn (array $allowed, CarbonImmutable $at): bool => ! $indefinitelyPaused
            && ($pausedUntil === null || $at->greaterThanOrEqualTo($pausedUntil))
            && ! in_array(false, $allowed, true);
        $allowedNow = $canOrder($state($instant), $instant);
        $nextOrderable = $allowedNow ? $instant : null;
        $nextChange = null;
        $currentCloses = null;
        foreach ($events as $event) {
            $after = $state($event);
            $before = $state($event->subMicrosecond());
            $isPauseExpiry = $pausedUntil !== null && $event->equalTo($pausedUntil);
            if ($after === $before && ! $isPauseExpiry) {
                continue;
            }
            $nextChange ??= $event;
            $eventAllowed = $canOrder($after, $event);
            if ($eventAllowed) {
                $nextOrderable ??= $event;
            } elseif ($allowedNow) {
                $currentCloses ??= $event;
            }
        }

        return ['allows_now' => $allowedNow, 'current_closes_at' => $currentCloses,
            'next_change_at' => $nextChange, 'next_orderable_at' => $nextOrderable];
    }

    /** @param list<Layer> $layers @return list<CarbonImmutable> */
    private function candidateDates(array $layers, CarbonImmutable $instant, ?CarbonImmutable $pausedUntil): array
    {
        $origins = [$instant->startOfDay()];
        if ($pausedUntil !== null && $pausedUntil->greaterThan($instant)) {
            $origins[] = $pausedUntil->setTimezone($instant->getTimezone())->startOfDay();
        }
        foreach ($layers as $layer) {
            foreach ($layer['exceptions'] as $exception) {
                $date = CarbonImmutable::parse($exception['local_date'], $instant->getTimezone())->startOfDay();
                if ($date->addDay()->greaterThan($instant)) {
                    $origins[] = $date;
                }
            }
        }
        $dates = [];
        foreach ($origins as $origin) {
            for ($offset = -1; $offset <= 8; $offset++) {
                $date = $origin->addDays($offset);
                if ($date->addDay()->greaterThan($instant)) {
                    $dates[$date->toDateString()] = $date;
                }
            }
        }
        ksort($dates);

        return array_values($dates);
    }

    /** @param Layer $layer @param array<string, ExceptionDay> $exceptions @return list<Window> */
    private function windows(array $layer, CarbonImmutable $date, array $exceptions): array
    {
        $exception = $exceptions[$date->toDateString()] ?? null;
        $intervals = [];
        if ($exception !== null) {
            if (! $exception['is_closed']) {
                foreach ($exception['intervals'] as $interval) {
                    $intervals[] = [$date, $interval];
                }
            }
        } else {
            if ($layer['weekly'] === [] && $layer['empty_allows']) {
                return [['start' => $date, 'end' => $date->addDay()]];
            }
            foreach ([$date->subDay(), $date] as $origin) {
                $previousException = $exceptions[$origin->toDateString()] ?? null;
                $source = $previousException === null ? $layer['weekly'] : ($previousException['is_closed'] ? [] : $previousException['intervals']);
                foreach ($source as $interval) {
                    if (isset($interval['day_of_week']) && $interval['day_of_week'] !== $origin->isoWeekday()) {
                        continue;
                    }
                    $intervals[] = [$origin, $interval];
                }
            }
        }
        $result = [];
        foreach ($intervals as [$origin, $interval]) {
            $window = $this->occurrence($origin, $interval);
            if ($window === null) {
                continue;
            }
            $start = $window['start']->max($date);
            $end = $window['end']->min($date->addDay());
            if ($start->lessThan($end)) {
                $result[] = ['start' => $start, 'end' => $end];
            }
        }

        return $result;
    }

    /** @param Interval $interval @return Window|null */
    private function occurrence(CarbonImmutable $date, array $interval): ?array
    {
        $opens = substr($interval['opens_at'], 0, 5);
        $closes = substr($interval['closes_at'], 0, 5);
        $endDate = $closes <= $opens ? $date->addDay() : $date;
        $start = $date->setTimeFromTimeString($opens);
        $end = $endDate->setTimeFromTimeString($closes);

        return $end->greaterThan($start) ? ['start' => $start, 'end' => $end] : null;
    }

    /** @param list<Window> $windows */
    private function contains(array $windows, CarbonImmutable $instant): bool
    {
        foreach ($windows as $window) {
            if ($instant->greaterThanOrEqualTo($window['start']) && $instant->lessThan($window['end'])) {
                return true;
            }
        }

        return false;
    }
}
