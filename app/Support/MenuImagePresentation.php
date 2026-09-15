<?php

declare(strict_types=1);

namespace App\Support;

final class MenuImagePresentation
{
    /** @return array{focal_x: int, focal_y: int, translations: array<string, array{alt: string, caption: string}>} */
    public static function normalize(?array $presentation): array
    {
        $translations = [];
        foreach (['en', 'lt', 'ru'] as $locale) {
            $entry = $presentation['translations'][$locale] ?? [];
            $translations[$locale] = [
                'alt' => is_string($entry['alt'] ?? null) ? trim($entry['alt']) : '',
                'caption' => is_string($entry['caption'] ?? null) ? trim($entry['caption']) : '',
            ];
        }

        return [
            'focal_x' => self::coordinate($presentation['focal_x'] ?? null),
            'focal_y' => self::coordinate($presentation['focal_y'] ?? null),
            'translations' => $translations,
        ];
    }

    public static function version(?array $presentation): string
    {
        return hash('sha256', json_encode([self::normalize($presentation), $presentation['revision'] ?? null], JSON_THROW_ON_ERROR));
    }

    /** @return array{alt: string, caption: string, object_position: string} */
    public static function localized(?array $presentation, string $locale, string $fallbackAlt): array
    {
        $normalized = self::normalize($presentation);
        $text = $normalized['translations'][$locale] ?? ['alt' => '', 'caption' => ''];

        return [
            'alt' => $text['alt'] !== '' ? $text['alt'] : $fallbackAlt,
            'caption' => $text['caption'],
            'object_position' => $normalized['focal_x'].'% '.$normalized['focal_y'].'%',
        ];
    }

    private static function coordinate(mixed $coordinate): int
    {
        return is_numeric($coordinate)
            ? max(0, min(100, (int) $coordinate)) : 50;
    }
}
