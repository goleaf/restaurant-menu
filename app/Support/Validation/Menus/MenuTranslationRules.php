<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Enums\SupportedLocale;

final class MenuTranslationRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function menuTranslations(string $field, int $nameMax, int $descriptionMax): array
    {
        $rules = [
            $field => ['required', 'array:en,lt,ru'],
        ];

        foreach (SupportedLocale::values() as $languageCode) {
            $nameField = $field.'.'.$languageCode.'.name';
            $descriptionField = $field.'.'.$languageCode.'.description';

            $rules[$field.'.'.$languageCode] = ['required', 'array:name,description'];
            $rules[$nameField] = [
                'required',
                'string',
                'max:'.$nameMax,
            ];
            $rules[$descriptionField] = ['nullable', 'string', 'max:'.$descriptionMax];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function translatedNames(string $field, int $nameMax = 160): array
    {
        $rules = [
            $field => ['required', 'array:en,lt,ru'],
        ];

        foreach (SupportedLocale::values() as $languageCode) {
            $rules[$field.'.'.$languageCode] = ['required', 'string', 'max:'.$nameMax];
        }

        return $rules;
    }
}
