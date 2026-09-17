<?php

declare(strict_types=1);

namespace App\Support\Validation\Availability;

use App\Support\Validation\NonOverlappingOpeningHours;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ScheduleRules
{
    /** @param array<array-key, mixed> $intervals @return list<array{day_of_week: int, starts_at: string, ends_at: string}> */
    public static function menu(array $intervals): array
    {
        $values = Validator::make(['weeklyIntervals' => $intervals], [
            'weeklyIntervals' => ['array', 'list', 'max:28'],
            'weeklyIntervals.*' => ['array:day_of_week,starts_at,ends_at'],
            'weeklyIntervals.*.day_of_week' => ['required', 'numeric', 'integer', 'between:1,7'],
            'weeklyIntervals.*.starts_at' => ['required', 'date_format:H:i'],
            'weeklyIntervals.*.ends_at' => ['required', 'date_format:H:i'],
        ], attributes: self::attributes())->validate()['weeklyIntervals'];
        $days = [];
        foreach ($values as $index => $interval) {
            $day = (int) $interval['day_of_week'];
            if ($interval['starts_at'] === $interval['ends_at']) {
                throw ValidationException::withMessages(["weeklyIntervals.$index.ends_at" => __('availability.errors.equal_interval')]);
            }
            $days[$day] ??= ['day_of_week' => $day, 'is_closed' => false, 'intervals' => []];
            $days[$day]['intervals'][] = ['opens_at' => $interval['starts_at'], 'closes_at' => $interval['ends_at']];
            if (count($days[$day]['intervals']) > 4) {
                throw ValidationException::withMessages(['weeklyIntervals' => __('availability.errors.interval_limit')]);
            }
            $values[$index]['day_of_week'] = $day;
        }
        Validator::make(['weeklyIntervals' => array_values($days)], ['weeklyIntervals' => [new NonOverlappingOpeningHours]])->validate();

        return $values;
    }

    /** @param array<array-key, mixed> $days @return list<array{day_of_week: int, is_closed: bool, intervals: list<array{opens_at: string, closes_at: string}>}> */
    public static function branch(array $days, bool $configured): array
    {
        $values = Validator::make(['openingHours' => $days], [
            'openingHours' => ['array', 'list', 'max:7'],
            'openingHours.*' => ['array:day_of_week,is_closed,intervals'],
            'openingHours.*.day_of_week' => ['required', 'numeric', 'integer', 'between:1,7', 'distinct'],
            'openingHours.*.is_closed' => ['required', 'boolean'],
            'openingHours.*.intervals' => ['present', 'array', 'list', 'max:4'],
            'openingHours.*.intervals.*' => ['array:opens_at,closes_at'],
            'openingHours.*.intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'openingHours.*.intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ], attributes: self::attributes())->validate()['openingHours'];
        if (! $configured) {
            return [];
        }
        foreach ($values as $index => $day) {
            if (! $day['is_closed'] && $day['intervals'] === []) {
                throw ValidationException::withMessages(["openingHours.$index.intervals" => __('availability.errors.interval_required')]);
            }
            foreach ($day['intervals'] as $position => $interval) {
                if (! $day['is_closed'] && $interval['opens_at'] === $interval['closes_at']) {
                    throw ValidationException::withMessages(["openingHours.$index.intervals.$position.closes_at" => __('availability.errors.equal_interval')]);
                }
            }
            $values[$index]['day_of_week'] = (int) $day['day_of_week'];
            $values[$index]['is_closed'] = (bool) $day['is_closed'];
        }
        Validator::make(['openingHours' => $values], ['openingHours' => [new NonOverlappingOpeningHours]])->validate();

        return $values;
    }

    /** @param array<array-key, mixed> $exceptions @return list<array{local_date: string, is_closed: bool, intervals: list<array{opens_at: string, closes_at: string}>}> */
    public static function exceptions(array $exceptions, ?string $timezone = null): array
    {
        $values = Validator::make(['exceptions' => $exceptions], [
            'exceptions' => ['array', 'list', 'max:366'],
            'exceptions.*' => ['array:local_date,is_closed,intervals'],
            'exceptions.*.local_date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'exceptions.*.is_closed' => ['required', 'boolean'],
            'exceptions.*.intervals' => ['present', 'array', 'list', 'max:4'],
            'exceptions.*.intervals.*' => ['array:opens_at,closes_at'],
            'exceptions.*.intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'exceptions.*.intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ], attributes: self::attributes())->validate()['exceptions'];
        foreach ($values as $index => $exception) {
            $intervals = $exception['intervals'];
            if (! $exception['is_closed'] && $intervals === []) {
                throw ValidationException::withMessages(["exceptions.$index.intervals" => __('availability.errors.interval_required')]);
            }
            $previousEnd = null;
            $orderedIntervals = $intervals;
            usort($orderedIntervals, static fn (array $a, array $b): int => strcmp($a['opens_at'], $b['opens_at']));
            foreach ($orderedIntervals as $interval) {
                if ($interval['opens_at'] >= $interval['closes_at'] || ($previousEnd !== null && $interval['opens_at'] < $previousEnd)) {
                    throw ValidationException::withMessages(["exceptions.$index.intervals" => __('availability.errors.date_intervals')]);
                }
                $previousEnd = $interval['closes_at'];
            }
            if ($timezone !== null && ! $exception['is_closed']) {
                foreach ($intervals as $position => $interval) {
                    foreach (['opens_at', 'closes_at'] as $field) {
                        $key = "exceptions.$index.intervals.$position.$field";
                        Validator::make(['exceptions' => [$index => ['intervals' => [$position => [$field => $exception['local_date'].'T'.$interval[$field]]]]]], [$key => [new BranchLocalDateTime($timezone)]])->validate();
                    }
                }
            }
            $values[$index]['is_closed'] = (bool) $exception['is_closed'];
            $values[$index]['intervals'] = $exception['is_closed'] ? [] : $intervals;
        }

        return $values;
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        return [
            'weeklyIntervals' => __('availability.fields.schedule'),
            'weeklyIntervals.*' => __('availability.fields.intervals'),
            'weeklyIntervals.*.day_of_week' => __('availability.fields.day'),
            'weeklyIntervals.*.starts_at' => __('availability.fields.start'),
            'weeklyIntervals.*.ends_at' => __('availability.fields.end'),
            'openingHours' => __('availability.fields.schedule'),
            'openingHours.*' => __('availability.fields.day'),
            'openingHours.*.day_of_week' => __('availability.fields.day'),
            'openingHours.*.is_closed' => __('availability.fields.closed'),
            'openingHours.*.intervals' => __('availability.fields.intervals'),
            'openingHours.*.intervals.*' => __('availability.fields.intervals'),
            'openingHours.*.intervals.*.opens_at' => __('availability.fields.start'),
            'openingHours.*.intervals.*.closes_at' => __('availability.fields.end'),
            'exceptions' => __('availability.fields.schedule'),
            'exceptions.*' => __('availability.fields.date'),
            'exceptions.*.local_date' => __('availability.fields.date'),
            'exceptions.*.is_closed' => __('availability.fields.closed'),
            'exceptions.*.intervals' => __('availability.fields.intervals'),
            'exceptions.*.intervals.*' => __('availability.fields.intervals'),
            'exceptions.*.intervals.*.opens_at' => __('availability.fields.start'),
            'exceptions.*.intervals.*.closes_at' => __('availability.fields.end'),
        ];
    }
}
