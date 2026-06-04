<?php

namespace App\Support;

use App\Enums\SupportedCurrency;

class MoneyFormatter
{
    public static function format(string|int|float|null $amount, ?string $currency = null): string
    {
        return self::formatCents(self::decimalToCents($amount), $currency);
    }

    public static function formatSigned(string|int|float|null $amount, ?string $currency = null): string
    {
        $cents = self::decimalToCents($amount);
        $sign = $cents > 0 ? '+' : ($cents < 0 ? '-' : '');

        return $sign.self::formatCents(abs($cents), $currency);
    }

    public static function formatCents(int $cents, ?string $currency = null): string
    {
        $currency = SupportedCurrency::from(SupportedCurrency::normalize($currency));
        $amount = number_format($cents / 100, 2, '.', ' ');

        if ($currency->usesPrefixSymbol()) {
            return $currency->symbol().$amount;
        }

        return $amount.' '.$currency->value;
    }

    public static function decimalToCents(string|int|float|null $amount): int
    {
        $normalized = number_format((float) ($amount ?? 0), 2, '.', '');

        return (int) round(((float) $normalized) * 100);
    }
}
