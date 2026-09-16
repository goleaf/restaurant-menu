<?php

declare(strict_types=1);

namespace App\Support\Validation\Common;

use App\Support\Validation\DecimalMoney;

final class MoneyRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function price(string $field = 'price'): array
    {
        return [
            $field => self::amount(),
        ];
    }

    /**
     * @return list<string|DecimalMoney>
     */
    public static function amount(bool $allowNegative = false): array
    {
        return [
            'required',
            'numeric',
            $allowNegative ? 'min:-999999.99' : 'min:0',
            'max:999999.99',
            'decimal:0,2',
            new DecimalMoney,
        ];
    }
}
