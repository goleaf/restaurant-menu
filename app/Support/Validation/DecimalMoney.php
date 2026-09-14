<?php

declare(strict_types=1);

namespace App\Support\Validation;

use App\Support\MoneyFormatter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use InvalidArgumentException;
use OverflowException;

final class DecimalMoney implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('validation.decimal_money')->translate();

            return;
        }

        try {
            MoneyFormatter::decimalToCents($value);
        } catch (InvalidArgumentException|OverflowException) {
            $fail('validation.decimal_money')->translate();
        }
    }
}
