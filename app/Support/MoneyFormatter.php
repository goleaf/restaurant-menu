<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SupportedCurrency;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use InvalidArgumentException;
use OverflowException;

final class MoneyFormatter
{
    public static function format(string|int|null $amount, ?string $currency = null): string
    {
        return self::formatCents(self::decimalToCents($amount), $currency);
    }

    public static function formatSigned(string|int|null $amount, ?string $currency = null): string
    {
        $cents = self::decimalToCents($amount);
        $sign = $cents > 0 ? '+' : ($cents < 0 ? '-' : '');

        return $sign.self::formatCents(self::absolute($cents), $currency);
    }

    public static function formatCents(int $cents, ?string $currency = null): string
    {
        $currency = SupportedCurrency::normalize($currency);
        $formatted = Number::currency(
            $cents / 100,
            in: $currency,
            locale: App::currentLocale(),
            precision: 2,
        );

        if (! is_string($formatted)) {
            throw new InvalidArgumentException('The money value could not be formatted for the selected locale.');
        }

        return $formatted;
    }

    public static function formatSignedCents(int $cents, ?string $currency = null): string
    {
        $sign = $cents > 0 ? '+' : '';

        return $sign.self::formatCents($cents, $currency);
    }

    public static function decimalToCents(string|int|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_int($amount)) {
            if ($amount > intdiv(PHP_INT_MAX, 100) || $amount < -intdiv(PHP_INT_MAX, 100)) {
                throw new OverflowException('The decimal amount exceeds the supported integer cents range.');
            }

            return $amount * 100;
        }

        $normalized = trim((string) $amount);

        if (preg_match('/^[+-]?\d+(?:[.,]\d{1,2})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Money must be a decimal number with no more than two fraction digits.');
        }

        $normalized = str_replace(',', '.', $normalized);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fractionCents = (int) str_pad($fraction, 2, '0');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $maximumWhole = (string) intdiv(PHP_INT_MAX - $fractionCents, 100);

        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)) {
            throw new OverflowException('The decimal amount exceeds the supported integer cents range.');
        }

        $cents = ((int) $whole * 100) + $fractionCents;

        return $negative ? -$cents : $cents;
    }

    public static function decimalToBasisPoints(string|int|null $percent): int
    {
        $basisPoints = self::decimalToCents($percent);

        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException('The percentage must be between 0 and 100.');
        }

        return $basisPoints;
    }

    public static function percentageOf(int $baseCents, int $basisPoints): int
    {
        if ($baseCents <= 0 || $basisPoints === 0) {
            return 0;
        }

        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException('Basis points must be between 0 and 10000.');
        }

        if ($baseCents > intdiv(PHP_INT_MAX - 5000, $basisPoints)) {
            throw new OverflowException('The percentage calculation exceeds the supported integer range.');
        }

        return self::roundedDivide($baseCents * $basisPoints, 10000);
    }

    public static function roundedDivide(int $dividend, int $divisor): int
    {
        if ($dividend < 0 || $divisor <= 0) {
            throw new InvalidArgumentException('Rounded money division requires a non-negative dividend and a positive divisor.');
        }

        $roundingOffset = intdiv($divisor, 2);

        if ($dividend > PHP_INT_MAX - $roundingOffset) {
            throw new OverflowException('The rounded money division exceeds the supported integer range.');
        }

        return intdiv($dividend + $roundingOffset, $divisor);
    }

    public static function centsToDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = self::absolute($cents);
        $formatted = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }

    private static function absolute(int $value): int
    {
        if ($value === PHP_INT_MIN) {
            throw new OverflowException('The money amount exceeds the supported integer range.');
        }

        return $value < 0 ? -$value : $value;
    }
}
