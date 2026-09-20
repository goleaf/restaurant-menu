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
        $languageCodes = array_values(array_intersect(SupportedLocale::values(), array_keys($translations)));
        if ($languageCodes === []) {
            return;
        }

        $existing = $item->translations()
            ->select(['id', 'menu_item_id', 'language_code', 'name', 'description', 'created_at', 'updated_at'])
            ->whereIn('language_code', $languageCodes)
            ->get()
            ->keyBy('language_code');

        foreach ($languageCodes as $languageCode) {
            $translation = $translations[$languageCode];
            $name = PlainText::optional($translation['name'] ?? null, 180, squish: true);
            $description = PlainText::optional($translation['description'] ?? null, 1200);

            if ($name === null) {
                $record = $existing->get($languageCode);
                if ($record !== null && $record->delete() !== true) {
                    throw new RuntimeException('The menu item translation deletion was cancelled.');
                }

                continue;
            }

            $attributes = ['name' => $name, 'description' => $description];
            $record = $existing->get($languageCode) ?? $item->translations()->createOrFirst(
                ['language_code' => $languageCode], $attributes,
            );
            if (! $record->exists || (! $record->wasRecentlyCreated && $record->fill($attributes)->save() !== true)) {
                throw new RuntimeException('The menu item translation write was cancelled.');
            }
        }
    }
}
