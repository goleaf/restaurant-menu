<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\SupportedLocale;
use App\Models\MenuItem;
use App\Support\PlainText;
use RuntimeException;

final class SyncMenuItemTranslationsAction
{
    /**
     * @param  array<string, array{name?: string|null, description?: string|null}>  $translations
     */
    public function handle(MenuItem $item, array $translations): void
    {
        foreach (SupportedLocale::values() as $languageCode) {
            if (! array_key_exists($languageCode, $translations)) {
                continue;
            }

            $translation = $translations[$languageCode];
            $name = PlainText::optional($translation['name'] ?? null, 180, squish: true);
            $description = PlainText::optional($translation['description'] ?? null, 1200);

            if ($name === null) {
                $record = $item->translations()->where('language_code', $languageCode)->first();
                if ($record !== null && $record->delete() !== true) {
                    throw new RuntimeException('The menu item translation deletion was cancelled.');
                }

                continue;
            }

            $record = $item->translations()->updateOrCreate(
                ['language_code' => $languageCode],
                ['name' => $name, 'description' => $description],
            );
            if (! $record->exists || $record->isDirty(['name', 'description'])) {
                throw new RuntimeException('The menu item translation write was cancelled.');
            }
        }
    }
}
