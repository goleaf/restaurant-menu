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
        if ($amount === null || $amount === '') {
            return 0;
        }

        $normalized = trim((string) $amount);

        if (! preg_match('/^[+-]?\d+(?:[.,]\d+)?$/', $normalized)) {
            $normalized = number_format((float) $amount, 2, '.', '');
        }

        $normalized = str_replace(',', '.', $normalized);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function centsToDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = abs($cents);
        $formatted = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
