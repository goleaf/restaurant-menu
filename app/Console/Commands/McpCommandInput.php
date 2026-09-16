<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Validation\ValidationException;

trait McpCommandInput
{
    private function integerInput(mixed $value, int $minimum = 1, int $maximum = PHP_INT_MAX): int
    {
        if ((! is_int($value) && ! is_string($value))
            || preg_match('/\A(?:0|[1-9][0-9]*)\z/', (string) $value) !== 1) {
            throw ValidationException::withMessages(['input' => __('mcp.cli.invalid_input')]);
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]);
        if ($integer === false) {
            throw ValidationException::withMessages(['input' => __('mcp.cli.invalid_input')]);
        }

        return $integer;
    }
}
