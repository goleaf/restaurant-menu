<?php

declare(strict_types=1);

namespace App\Support\Orders;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class IdempotencyKey
{
    private function __construct(public string $value) {}

    public static function from(?string $value, string $field = 'idempotency_key'): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (strlen($value) > 64 || ! Str::isUuid($value)) {
            throw ValidationException::withMessages([
                $field => __('errors.types.validation_error.message'),
            ]);
        }

        return new self(Str::lower($value));
    }
}
