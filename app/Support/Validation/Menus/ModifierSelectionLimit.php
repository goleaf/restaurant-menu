<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class ModifierSelectionLimit implements DataAwareRule, ValidationRule
{
    /** @var array<string,mixed> */
    private array $data = [];

    public function __construct(private readonly string $minimumField) {}

    /** @param array<string,mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $minimum = data_get($this->data, $this->minimumField);
        if (is_numeric($value) && is_numeric($minimum) && (int) $value !== 0 && (int) $value < (int) $minimum) {
            $fail(__('dish.errors.maximum_below_minimum'));
        }
    }
}
