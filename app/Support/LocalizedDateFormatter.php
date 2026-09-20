<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\App;

final class LocalizedDateFormatter
{
    public static function date(?DateTimeInterface $value, ?DisplayPreferences $preferences = null): ?string
    {
        return self::format($value, match (($preferences ?? DisplayPreferences::current())->dateFormat) {
            'd.m.Y' => 'DD.MM.YYYY',
            'd/m/Y' => 'DD/MM/YYYY',
            'm/d/Y' => 'MM/DD/YYYY',
            'Y-m-d' => 'YYYY-MM-DD',
            default => 'L',
        });
    }

    public static function dateTime(?DateTimeInterface $value, ?DisplayPreferences $preferences = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $preferences ??= DisplayPreferences::current();

        return self::date($value, $preferences).' '.self::time($value, $preferences);
    }

    public static function time(?DateTimeInterface $value, ?DisplayPreferences $preferences = null): ?string
    {
        return self::format($value, match (($preferences ?? DisplayPreferences::current())->timeFormat) {
            '24h' => 'HH:mm',
            '12h' => 'h:mm A',
            default => 'LT',
        });
    }

    public static function timeWithSeconds(?DateTimeInterface $value, ?DisplayPreferences $preferences = null): ?string
    {
        return self::format($value, match (($preferences ?? DisplayPreferences::current())->timeFormat) {
            '24h' => 'HH:mm:ss',
            '12h' => 'h:mm:ss A',
            default => 'LTS',
        });
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
