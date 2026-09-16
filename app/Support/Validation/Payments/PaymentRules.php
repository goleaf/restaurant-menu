<?php

declare(strict_types=1);

namespace App\Support\Validation\Payments;

use App\Enums\ManualPaymentMethod;
use App\Support\Validation\DecimalMoney;
use Illuminate\Validation\Rule;

final class PaymentRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function manualPaymentAmount(string $field = 'tipsAmount'): array
    {
        return [
            $field => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2', new DecimalMoney],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function paymentMethod(string $field = 'paymentMethod'): array
    {
        return [
            $field => ['required', 'string', Rule::in(ManualPaymentMethod::values())],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function paymentNote(string $field = 'paymentNote'): array
    {
        return [
            $field => ['nullable', 'string', 'max:500'],
        ];
    }
}
