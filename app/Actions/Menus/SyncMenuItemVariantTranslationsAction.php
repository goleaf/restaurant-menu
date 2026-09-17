<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\SupportedLocale;
use App\Models\MenuItemVariant;
use App\Support\PlainText;

class SyncMenuItemVariantTranslationsAction
{
    /**
     * @param  array<string, string|null>  $translations
     */
    public function handle(MenuItemVariant $variant, array $translations): void
    {
        $variant->loadMissing('translations');
        $existing = $variant->translations->keyBy('language_code');
        foreach (SupportedLocale::values() as $languageCode) {
            if (! array_key_exists($languageCode, $translations)) {
                continue;
            }

            $name = PlainText::optional($translations[$languageCode] ?? null, 160, squish: true);

            if ($name === null) {
                $translation = $existing->get($languageCode);
                if ($translation !== null && $translation->delete() !== true) {
                    throw new \RuntimeException('The variant translation could not be deleted.');
                }

                continue;
            }

            $translation = $existing->get($languageCode) ?? $variant->translations()->make(['language_code' => $languageCode]);
            $translation->name = $name;
            if ($translation->save() !== true) {
                throw new \RuntimeException('The variant translation could not be saved.');
            }
        }
    }
}
