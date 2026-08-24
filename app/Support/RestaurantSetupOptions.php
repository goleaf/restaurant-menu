<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AreaNodeType;
use App\Enums\SupportedCurrency;
use DateTimeImmutable;
use DateTimeZone;

final class RestaurantSetupOptions
{
    private const ISO_ALPHA_2 = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';

    /**
     * @return list<string>
     */
    public static function countryCodes(): array
    {
        return explode(' ', self::ISO_ALPHA_2);
    }

    /**
     * @return array<string, string>
     */
    public static function countryOptions(string $locale): array
    {
        $options = [];

        foreach (self::countryCodes() as $countryCode) {
            $options[$countryCode] = self::countryName($countryCode, $locale);
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public static function countryName(string $countryCode, string $locale = 'en'): string
    {
        $normalizedCode = strtoupper(trim($countryCode));
        $normalizedLocale = in_array($locale, ['en', 'lt', 'ru'], true) ? $locale : 'en';

        if (! in_array($normalizedCode, self::countryCodes(), true)) {
            return $normalizedCode;
        }

        if (class_exists(\Locale::class)) {
            $localizedName = \Locale::getDisplayRegion('-'.$normalizedCode, $normalizedLocale);

            if (is_string($localizedName) && trim($localizedName) !== '' && $localizedName !== $normalizedCode) {
                return $localizedName;
            }
        }

        return $normalizedCode;
    }

    public static function countryCode(string $countryName): string
    {
        $normalizedName = trim($countryName);
        $normalizedComparableName = mb_strtolower($normalizedName);

        foreach (self::countryCodes() as $countryCode) {
            foreach (['en', 'lt', 'ru'] as $locale) {
                if (mb_strtolower(self::countryName($countryCode, $locale)) === $normalizedComparableName) {
                    return $countryCode;
                }
            }
        }

        $normalizedCode = strtoupper($normalizedName);

        return in_array($normalizedCode, self::countryCodes(), true) ? $normalizedCode : '';
    }

    public static function defaultTimezone(?string $configuredTimezone): string
    {
        $normalizedTimezone = trim((string) $configuredTimezone);

        return in_array($normalizedTimezone, DateTimeZone::listIdentifiers(), true)
            ? $normalizedTimezone
            : 'UTC';
    }

    /**
     * @return array<string, string>
     */
    public static function timezoneOptions(): array
    {
        $now = new DateTimeImmutable;
        $options = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $timezone = new DateTimeZone($identifier);
            $offsetSeconds = $timezone->getOffset($now);
            $sign = $offsetSeconds < 0 ? '-' : '+';
            $absoluteOffset = abs($offsetSeconds);
            $hours = intdiv($absoluteOffset, 3600);
            $minutes = intdiv($absoluteOffset % 3600, 60);

            $options[$identifier] = sprintf('(UTC%s%02d:%02d) %s', $sign, $hours, $minutes, $identifier);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function areaTypeOptions(): array
    {
        return [
            AreaNodeType::Group->value => __('ui.livewire.organizations.brands.branches.areas.gruppa_zon'),
            AreaNodeType::Floor->value => __('ui.livewire.organizations.brands.branches.areas.etaz'),
            AreaNodeType::Hall->value => __('ui.livewire.organizations.brands.branches.areas.zal'),
            AreaNodeType::Terrace->value => __('ui.livewire.organizations.brands.branches.areas.terrasa'),
            AreaNodeType::VipRoom->value => __('ui.livewire.organizations.brands.branches.areas.vip_zal'),
            AreaNodeType::BarArea->value => __('navigation.bar'),
            AreaNodeType::BanquetHall->value => __('ui.livewire.organizations.brands.branches.areas.banquet'),
            AreaNodeType::Room->value => __('reports.service_point_types.room'),
            AreaNodeType::HotelArea->value => __('qr.print.presets.hotel.label'),
            AreaNodeType::PickupArea->value => __('ui.livewire.organizations.brands.branches.areas.pickup'),
            AreaNodeType::DeliveryArea->value => __('ui.livewire.organizations.brands.branches.areas.delivery'),
            AreaNodeType::Custom->value => __('ui.onboarding.restaurant_setup.area_types.custom'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function areaIconOptions(): array
    {
        return [
            'folder' => __('ui.livewire.organizations.brands.branches.areas.folder'),
            'building-office' => __('ui.livewire.organizations.brands.branches.areas.building'),
            'rectangle-group' => __('ui.livewire.organizations.brands.branches.areas.hall'),
            'sun' => __('ui.livewire.organizations.brands.branches.areas.terrace'),
            'sparkles' => __('ui.livewire.organizations.brands.branches.areas.vip'),
            'beaker' => __('navigation.bar'),
            'cake' => __('ui.livewire.organizations.brands.branches.areas.banquet'),
            'home' => __('reports.service_point_types.room'),
            'building-office-2' => __('qr.print.presets.hotel.label'),
            'shopping-bag' => __('ui.livewire.organizations.brands.branches.areas.pickup'),
            'truck' => __('ui.livewire.organizations.brands.branches.areas.delivery'),
            'bookmark' => __('ui.onboarding.restaurant_setup.area_types.custom'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function currencyOptions(): array
    {
        $options = [];

        foreach (SupportedCurrency::cases() as $currency) {
            $options[$currency->value] = self::currencyLabel($currency);
        }

        return $options;
    }

    private static function currencyLabel(SupportedCurrency $currency): string
    {
        return match ($currency) {
            SupportedCurrency::Euro => __('ui.onboarding.restaurant_setup.currencies.eur'),
            SupportedCurrency::UnitedStatesDollar => __('ui.onboarding.restaurant_setup.currencies.usd'),
            SupportedCurrency::PoundSterling => __('ui.onboarding.restaurant_setup.currencies.gbp'),
            SupportedCurrency::PolishZloty => __('ui.onboarding.restaurant_setup.currencies.pln'),
            SupportedCurrency::CzechKoruna => __('ui.onboarding.restaurant_setup.currencies.czk'),
            SupportedCurrency::DanishKrone => __('ui.onboarding.restaurant_setup.currencies.dkk'),
            SupportedCurrency::NorwegianKrone => __('ui.onboarding.restaurant_setup.currencies.nok'),
            SupportedCurrency::SwedishKrona => __('ui.onboarding.restaurant_setup.currencies.sek'),
            SupportedCurrency::SwissFranc => __('ui.onboarding.restaurant_setup.currencies.chf'),
            SupportedCurrency::UkrainianHryvnia => __('ui.onboarding.restaurant_setup.currencies.uah'),
            SupportedCurrency::GeorgianLari => __('ui.onboarding.restaurant_setup.currencies.gel'),
            SupportedCurrency::TurkishLira => __('ui.onboarding.restaurant_setup.currencies.try'),
            SupportedCurrency::CanadianDollar => __('ui.onboarding.restaurant_setup.currencies.cad'),
            SupportedCurrency::AustralianDollar => __('ui.onboarding.restaurant_setup.currencies.aud'),
        };
    }
}
