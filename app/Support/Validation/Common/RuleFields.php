<?php

declare(strict_types=1);

namespace App\Support\Validation\Common;

final class RuleFields
{
    public static function name(string $prefix, string $name): string
    {
        if ($prefix === '') {
            return $name;
        }

        return $prefix.ucfirst($name);
    }
}
