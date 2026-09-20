<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\SupportedLocale;
use App\Models\MenuCategory;
use App\Support\PlainText;
use RuntimeException;

final class SyncMenuCategoryTranslationsAction
{
    /**
     * @param  array<string, array{name?: string|null, description?: string|null}>  $translations
     */
    public function handle(MenuCategory $category, array $translations): void
    {
        $languageCodes = array_values(array_intersect(SupportedLocale::values(), array_keys($translations)));
        if ($languageCodes === []) {
            return;
        }

        $existing = $category->translations()
            ->select(['id', 'menu_category_id', 'language_code', 'name', 'description', 'created_at', 'updated_at'])
            ->whereIn('language_code', $languageCodes)
            ->get()
            ->keyBy('language_code');

        foreach ($languageCodes as $languageCode) {
            $translation = $translations[$languageCode];
            $name = PlainText::optional($translation['name'] ?? null, 160, squish: true);
            $description = PlainText::optional($translation['description'] ?? null, 1000);

            if ($name === null) {
                $record = $existing->get($languageCode);
                if ($record !== null && $record->delete() !== true) {
                    throw new RuntimeException('The menu category translation deletion was cancelled.');
                }

                continue;
            }

            $attributes = ['name' => $name, 'description' => $description];
            $record = $existing->get($languageCode) ?? $category->translations()->createOrFirst(
                ['language_code' => $languageCode], $attributes,
            );
            if (! $record->exists || (! $record->wasRecentlyCreated && $record->fill($attributes)->save() !== true)) {
                throw new RuntimeException('The menu category translation write was cancelled.');
            }
        }
    }
}
