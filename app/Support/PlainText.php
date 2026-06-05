<?php

namespace App\Support;

use Stringable;

class PlainText
{
    public static function required(mixed $value, int $maxLength, bool $squish = false): string
    {
        return self::normalize($value, $maxLength, $squish);
    }

    public static function optional(mixed $value, int $maxLength, bool $squish = false): ?string
    {
        $normalized = self::normalize($value, $maxLength, $squish);

        return $normalized === '' ? null : $normalized;
    }

    private static function normalize(mixed $value, int $maxLength, bool $squish): string
    {
        $text = self::stringValue($value);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = strip_tags($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = $squish ? str($text)->squish()->toString() : trim($text);

        return $maxLength > 0 ? mb_substr($text, 0, $maxLength) : $text;
    }

    private static function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }
}
