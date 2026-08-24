<?php

declare(strict_types=1);

namespace App\Support\Orders;

use Illuminate\Validation\ValidationException;

final readonly class OrderItemQuantity
{
    public const int MIN = 1;

    public const int MAX = 99;

    private function __construct(public int $value) {}

    public static function from(int $value, string $field = 'quantity'): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw ValidationException::withMessages([
                $field => __('ui.actions.draftorders.updateguestdraftorderitemaction.kolicestvo_dolzno_by'),
            ]);
        }

        return new self($value);
    }
}
