<?php

declare(strict_types=1);

namespace App\Support\Validation;

use Illuminate\Validation\ValidationException;

final class IndependentSectionValidation
{
    /** @param array<string, list<string>> $existingErrors */
    public static function preserve(ValidationException $exception, array $existingErrors, string $prefix): ValidationException
    {
        $unrelated = array_filter($existingErrors, static fn (string $field): bool => $field !== $prefix && ! str_starts_with($field, $prefix.'.'), ARRAY_FILTER_USE_KEY);
        foreach ($unrelated as $field => $messages) {
            foreach ($messages as $message) {
                $exception->validator->errors()->add($field, $message);
            }
        }

        return $exception;
    }
}
