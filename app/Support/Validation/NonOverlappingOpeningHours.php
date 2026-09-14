<?php

declare(strict_types=1);

namespace App\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class NonOverlappingOpeningHours implements ValidationRule
{
    private const int MINUTES_PER_DAY = 1440;

    private const int MINUTES_PER_WEEK = 10080;

    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || count($value) > 7) {
            return;
        }

        $segments = [];

        foreach ($value as $day) {
            if (! is_array($day) || ! in_array($day['is_closed'] ?? false, [false, 0, '0'], true)) {
                continue;
            }

            $dayOfWeek = $day['day_of_week'] ?? null;

            if ((! is_int($dayOfWeek) && ! is_string($dayOfWeek)) || ! preg_match('/^[1-7]$/D', (string) $dayOfWeek)) {
                continue;
            }

            $intervals = $day['intervals'] ?? null;

            if (! is_array($intervals) || count($intervals) > 4) {
                continue;
            }

            foreach ($intervals as $interval) {
                if (! is_array($interval)) {
                    continue;
                }

                $opensAt = $this->minutes($interval['opens_at'] ?? null);
                $closesAt = $this->minutes($interval['closes_at'] ?? null);

                if ($opensAt === null || $closesAt === null || $opensAt === $closesAt) {
                    continue;
                }

                $dayStart = ((int) $dayOfWeek - 1) * self::MINUTES_PER_DAY;
                $start = $dayStart + $opensAt;
                $end = $dayStart + $closesAt + ($closesAt < $opensAt ? self::MINUTES_PER_DAY : 0);

                if ($end > self::MINUTES_PER_WEEK) {
                    $segments[] = [0, $end - self::MINUTES_PER_WEEK];
                    $end = self::MINUTES_PER_WEEK;
                }

                $segments[] = [$start, $end];
            }
        }

        usort($segments, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $previousEnd = 0;

        foreach ($segments as [$start, $end]) {
            if ($start < $previousEnd) {
                $fail('branches.opening_hours.errors.overlap')->translate();

                return;
            }

            $previousEnd = $end;
        }
    }

    private function minutes(mixed $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) {
            return null;
        }

        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }
}
