<?php

declare(strict_types=1);

use App\Support\Orders\IdempotencyKey;
use App\Support\Orders\OrderItemQuantity;
use Illuminate\Validation\ValidationException;

test('order item quantity accepts only the canonical bounded integer range', function (): void {
    expect(OrderItemQuantity::from(1)->value)->toBe(1)
        ->and(OrderItemQuantity::from(99)->value)->toBe(99)
        ->and(fn (): OrderItemQuantity => OrderItemQuantity::from(0))
        ->toThrow(ValidationException::class)
        ->and(fn (): OrderItemQuantity => OrderItemQuantity::from(100))
        ->toThrow(ValidationException::class);
});

test('idempotency keys accept only canonical UUID values', function (): void {
    $value = '018FDC1B-7A43-7D8E-A8B5-49EF5CC91671';

    expect(IdempotencyKey::from($value)?->value)->toBe(strtolower($value))
        ->and(IdempotencyKey::from(null))->toBeNull()
        ->and(fn (): ?IdempotencyKey => IdempotencyKey::from('not-a-uuid'))
        ->toThrow(ValidationException::class);
});
