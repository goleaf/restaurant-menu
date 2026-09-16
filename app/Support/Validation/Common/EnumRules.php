<?php

declare(strict_types=1);

namespace App\Support\Validation\Common;

use Illuminate\Validation\Rule;

final class EnumRules
{
    /**
     * @return list<mixed>
     */
    public static function text(array $values, bool $required): array
    {
        $rules = [$required ? 'required' : 'nullable', 'string'];

        if ($values !== []) {
            $rules[] = Rule::in($values);
        }

        return $rules;
    }
}
