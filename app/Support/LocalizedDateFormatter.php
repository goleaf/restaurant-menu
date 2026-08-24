<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\App;

final class LocalizedDateFormatter
{
    public static function date(?DateTimeInterface $value): ?string
    {
        return self::format($value, 'L');
    }

    public static function dateTime(?DateTimeInterface $value): ?string
    {
        return self::format($value, 'L LT');
    }

    public static function time(?DateTimeInterface $value): ?string
    {
        return self::format($value, 'LT');
    }

    public static function timeWithSeconds(?DateTimeInterface $value): ?string
    {
        return self::format($value, 'LTS');
    }

    public static function relative(?DateTimeInterface $value): ?string
    {
        return self::carbon($value)?->locale(App::currentLocale())->diffForHumans();
    }

    private static function format(?DateTimeInterface $value, string $format): ?string
    {
        return self::carbon($value)?->locale(App::currentLocale())->isoFormat($format);
    }

    private static function carbon(?DateTimeInterface $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonInterface ? $value : CarbonImmutable::instance($value);
    }
}
