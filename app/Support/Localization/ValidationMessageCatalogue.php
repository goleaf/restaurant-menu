<?php

declare(strict_types=1);

namespace App\Support\Localization;

use Illuminate\Support\Arr;
use ReflectionClass;

final class ValidationMessageCatalogue
{
    /** @return list<string> */
    public static function keys(): array
    {
        $directory = dirname((new ReflectionClass(\Illuminate\Translation\Translator::class))->getFileName());
        $messages = require $directory.'/lang/en/validation.php';
        unset($messages['custom'], $messages['attributes']);

        return array_map(fn (string $key): string => 'validation.'.$key, array_keys(Arr::dot($messages)));
    }
}
