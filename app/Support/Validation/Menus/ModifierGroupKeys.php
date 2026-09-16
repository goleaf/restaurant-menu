<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ModifierGroupKeys implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $groupId) {
            $identifier = filter_var($groupId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($identifier === false || (string) $identifier !== (string) $groupId) {
                $fail('validation.rules.modifier_group_keys')->translate();

                return;
            }
        }
    }
}
