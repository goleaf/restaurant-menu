<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use NumberFormatter;

final class LocalizedNumberFormatter
{
    public static function decimal(int|float $value, int $precision = 0, ?DisplayPreferences $preferences = null): string
    {
        $formatted = self::formatter(NumberFormatter::DECIMAL, $precision, $preferences)->format($value);

        if ($formatted === false) {
            throw new InvalidArgumentException('The number could not be formatted.');
        }

        return $formatted;
    }

    public static function formatter(int $style, int $precision, ?DisplayPreferences $preferences = null): NumberFormatter
    {
        $formatter = new NumberFormatter(App::currentLocale(), $style);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
        $separators = ($preferences ?? DisplayPreferences::current())->numberSeparators();

        if ($separators !== null) {
            $formatter->setSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $separators['decimal']);
            $formatter->setSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL, $separators['group']);
            $formatter->setSymbol(NumberFormatter::MONETARY_SEPARATOR_SYMBOL, $separators['decimal']);
            $formatter->setSymbol(NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL, $separators['group']);
        }

        return $formatter;
    }
}
