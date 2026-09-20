<?php

declare(strict_types=1);

namespace App\Support\Branches;

use App\Enums\SupportedLocale;
use App\Models\Branch;
use JsonException;

final class BranchPublicContent
{
    public static function translations(Branch $branch): mixed
    {
        $stored = $branch->getAttributes()['public_translations'] ?? null;
        if (! is_string($stored)) {
            return $stored;
        }

        try {
            return json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $stored;
        }
    }

    public static function text(Branch $branch, string $field, string $language, string $defaultLanguage): ?string
    {
        $translations = self::translations($branch);
        $translations = is_array($translations) ? $translations : [];
        foreach (array_unique([SupportedLocale::normalize($language), SupportedLocale::normalize($defaultLanguage)]) as $locale) {
            $translation = $translations[$locale] ?? null;
            $value = is_array($translation) ? ($translation[$field] ?? null) : null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        $legacy = $branch->getAttribute($field === 'name' ? 'public_name' : 'public_description');

        return is_string($legacy) && trim($legacy) !== '' ? $legacy : null;
    }
}
