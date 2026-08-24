<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\SupportedLocale;
use App\Models\MenuItem;
use App\Support\PlainText;

final class SyncMenuItemTranslationsAction
{
    /**
     * @param  array<string, array{name?: string|null, description?: string|null}>  $translations
     */
    public function handle(MenuItem $item, array $translations): void
    {
        foreach (SupportedLocale::values() as $languageCode) {
            $translation = $translations[$languageCode] ?? [];
            $name = PlainText::optional($translation['name'] ?? null, 180, squish: true);
            $description = PlainText::optional($translation['description'] ?? null, 1200);

            if ($name === null) {
                $item->translations()->where('language_code', $languageCode)->delete();

                continue;
            }

            $item->translations()->updateOrCreate(
                ['language_code' => $languageCode],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}
