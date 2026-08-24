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
        return __(match ($this) {
            self::Euro => 'ui.onboarding.restaurant_setup.currencies.eur',
            self::UnitedStatesDollar => 'ui.onboarding.restaurant_setup.currencies.usd',
            self::PoundSterling => 'ui.onboarding.restaurant_setup.currencies.gbp',
            self::PolishZloty => 'ui.onboarding.restaurant_setup.currencies.pln',
            self::CzechKoruna => 'ui.onboarding.restaurant_setup.currencies.czk',
            self::DanishKrone => 'ui.onboarding.restaurant_setup.currencies.dkk',
            self::NorwegianKrone => 'ui.onboarding.restaurant_setup.currencies.nok',
            self::SwedishKrona => 'ui.onboarding.restaurant_setup.currencies.sek',
            self::SwissFranc => 'ui.onboarding.restaurant_setup.currencies.chf',
            self::UkrainianHryvnia => 'ui.onboarding.restaurant_setup.currencies.uah',
            self::GeorgianLari => 'ui.onboarding.restaurant_setup.currencies.gel',
            self::TurkishLira => 'ui.onboarding.restaurant_setup.currencies.try',
            self::CanadianDollar => 'ui.onboarding.restaurant_setup.currencies.cad',
            self::AustralianDollar => 'ui.onboarding.restaurant_setup.currencies.aud',
        });
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
