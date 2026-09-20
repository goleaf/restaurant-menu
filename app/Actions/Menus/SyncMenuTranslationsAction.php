<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\SupportedLocale;
use App\Models\Menu;
use App\Support\PlainText;
use RuntimeException;

class SyncMenuTranslationsAction
{
    /** @param array<string, string|null> $translations */
    public function handle(Menu $menu, array $translations): void
    {
        $languageCodes = array_values(array_intersect(SupportedLocale::values(), array_keys($translations)));
        if ($languageCodes === []) {
            return;
        }

        $existing = $menu->translations()
            ->select(['id', 'menu_id', 'language_code', 'name', 'created_at', 'updated_at'])
            ->whereIn('language_code', $languageCodes)
            ->get()
            ->keyBy('language_code');

        foreach ($languageCodes as $languageCode) {
            $attributes = ['name' => PlainText::required($translations[$languageCode] ?? null, 160, squish: true)];
            $record = $existing->get($languageCode) ?? $menu->translations()->createOrFirst(
                ['language_code' => $languageCode], $attributes,
            );

            if (! $record->exists || (! $record->wasRecentlyCreated && $record->fill($attributes)->save() !== true)) {
                throw new RuntimeException('The menu translation write was cancelled.');
            }
        }
    }
}
