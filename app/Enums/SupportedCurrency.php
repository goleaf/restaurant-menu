<?php

namespace App\Enums;

enum SupportedCurrency: string
{
    case Euro = 'EUR';
    case UnitedStatesDollar = 'USD';
    case PoundSterling = 'GBP';
    case PolishZloty = 'PLN';
    case CzechKoruna = 'CZK';
    case DanishKrone = 'DKK';
    case NorwegianKrone = 'NOK';
    case SwedishKrona = 'SEK';
    case SwissFranc = 'CHF';
    case UkrainianHryvnia = 'UAH';
    case GeorgianLari = 'GEL';
    case TurkishLira = 'TRY';
    case CanadianDollar = 'CAD';
    case AustralianDollar = 'AUD';

    public function label(): string
    {
        return match ($this) {
            self::Euro => 'EUR - Euro',
            self::UnitedStatesDollar => 'USD - US dollar',
            self::PoundSterling => 'GBP - Pound sterling',
            self::PolishZloty => 'PLN - Polish zloty',
            self::CzechKoruna => 'CZK - Czech koruna',
            self::DanishKrone => 'DKK - Danish krone',
            self::NorwegianKrone => 'NOK - Norwegian krone',
            self::SwedishKrona => 'SEK - Swedish krona',
            self::SwissFranc => 'CHF - Swiss franc',
            self::UkrainianHryvnia => 'UAH - Ukrainian hryvnia',
            self::GeorgianLari => 'GEL - Georgian lari',
            self::TurkishLira => 'TRY - Turkish lira',
            self::CanadianDollar => 'CAD - Canadian dollar',
            self::AustralianDollar => 'AUD - Australian dollar',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Euro => '€',
            self::UnitedStatesDollar,
            self::CanadianDollar,
            self::AustralianDollar => '$',
            self::PoundSterling => '£',
            self::SwissFranc => 'CHF',
            self::UkrainianHryvnia => '₴',
            self::TurkishLira => '₺',
            self::GeorgianLari => 'GEL',
            default => $this->value,
        };
    }

    public function usesPrefixSymbol(): bool
    {
        return in_array($this, [
            self::Euro,
            self::UnitedStatesDollar,
            self::PoundSterling,
            self::UkrainianHryvnia,
            self::TurkishLira,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $currency): string => $currency->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $currency): array => [$currency->value => $currency->label()])
            ->all();
    }

    public static function clean(?string $currency): string
    {
        return strtoupper(trim((string) $currency));
    }

    public static function normalize(?string $currency, ?string $fallback = 'EUR'): string
    {
        $cleanCurrency = self::clean($currency);

        if (self::tryFrom($cleanCurrency) instanceof self) {
            return $cleanCurrency;
        }

        $cleanFallback = self::clean($fallback);

        return self::tryFrom($cleanFallback) instanceof self ? $cleanFallback : self::Euro->value;
    }

    public static function isSupported(?string $currency): bool
    {
        return self::tryFrom(self::clean($currency)) instanceof self;
    }
}
