<?php

declare(strict_types=1);

namespace App\Support\Validation\Orders;

use App\Support\Orders\OrderItemQuantity;

final class OrderInputRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function quantity(string $field): array
    {
        return [
            $field => ['required', 'integer', 'min:'.OrderItemQuantity::MIN, 'max:'.OrderItemQuantity::MAX],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function waiterRejectionReason(string $field = 'rejectionReason'): array
    {
        return [
            $field => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
