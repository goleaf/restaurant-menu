<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\SupportedLocale;
use App\Models\MenuCategory;
use App\Support\PlainText;

final class SyncMenuCategoryTranslationsAction
{
    /**
     * @param  array<string, array{name?: string|null, description?: string|null}>  $translations
     */
    public function handle(MenuCategory $category, array $translations): void
    {
        foreach (SupportedLocale::values() as $languageCode) {
            $translation = $translations[$languageCode] ?? [];
            $name = PlainText::optional($translation['name'] ?? null, 160, squish: true);
            $description = PlainText::optional($translation['description'] ?? null, 1000);

            if ($name === null) {
                $category->translations()->where('language_code', $languageCode)->delete();

                continue;
            }

            $category->translations()->updateOrCreate(
                ['language_code' => $languageCode],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}
