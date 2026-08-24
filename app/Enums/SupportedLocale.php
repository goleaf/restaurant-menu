<?php

namespace App\Enums;

enum SupportedLocale: string
{
    case Russian = 'ru';
    case English = 'en';
    case Lithuanian = 'lt';

    public function label(): string
    {
        return __(match ($this) {
            self::Russian => 'ui.languages.ru',
            self::English => 'ui.languages.en',
            self::Lithuanian => 'ui.languages.lt',
        });
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $locale): string => $locale->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $locale): array => [$locale->value => $locale->label()])
            ->all();
    }

    public static function normalize(?string $locale, ?string $fallback = 'en'): string
    {
        $normalized = self::baseCode($locale);

        if (self::tryFrom($normalized) instanceof self) {
            return $normalized;
        }

        $fallback = self::baseCode($fallback);

        return self::tryFrom($fallback) instanceof self ? $fallback : self::English->value;
    }

    public static function isSupported(?string $locale): bool
    {
        return self::tryFrom(self::baseCode($locale)) instanceof self;
    }

    private static function baseCode(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        $locale = str_replace('_', '-', $locale);

        return explode('-', $locale)[0];
    }
}
