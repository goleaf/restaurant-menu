<?php

declare(strict_types=1);

namespace App\Support\Validation\Availability;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

final class BranchLocalDateTime implements ValidationRule
{
    public function __construct(private readonly string $timezone, private readonly ?CarbonImmutable $after = null) {}

    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value)) {
            $fail('availability.errors.local_datetime')->translate();

            return;
        }
        try {
            $zone = new DateTimeZone($this->timezone);
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $zone);
            if ($parsed === false || $parsed->format('Y-m-d\TH:i') !== $value) {
                $fail('availability.errors.local_datetime')->translate();

                return;
            }
            $wall = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('UTC'));
            $candidates = [];
            foreach ($zone->getTransitions($parsed->getTimestamp() - 86400, $parsed->getTimestamp() + 86400) ?: [] as $transition) {
                $candidate = (new DateTimeImmutable('@'.($wall->getTimestamp() - $transition['offset'])))->setTimezone($zone);
                if ($candidate->format('Y-m-d\TH:i') === $value) {
                    $candidates[$candidate->getTimestamp()] = true;
                }
            }
            if (count($candidates) > 1) {
                $fail('availability.errors.ambiguous_datetime')->translate();

                return;
            }
            if ($this->after !== null && self::parse($value, $this->timezone)->lessThanOrEqualTo($this->after)) {
                $fail('availability.errors.future_deadline')->translate();
            }
        } catch (Throwable) {
            $fail('availability.errors.local_datetime')->translate();
        }
    }

    /** Call only after this rule has validated the value. */
    public static function parse(string $value, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
    }
}
