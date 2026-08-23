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
        foreach (SupportedLocale::values() as $languageCode) {
            $name = PlainText::optional($translations[$languageCode] ?? null, 160, squish: true);

            if ($name === null) {
                $variant->translations()->where('language_code', $languageCode)->delete();

                continue;
            }

            $variant->translations()->updateOrCreate(
                ['language_code' => $languageCode],
                ['name' => $name],
            );
        }
    }
}
