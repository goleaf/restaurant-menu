<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\SupportedLocale;
use App\Models\Menu;
use App\Support\PlainText;

class SyncMenuTranslationsAction
{
    /** @param array<string, string|null> $translations */
    public function handle(Menu $menu, array $translations): void
    {
        foreach (SupportedLocale::values() as $languageCode) {
            $menu->translations()->updateOrCreate(
                ['language_code' => $languageCode],
                ['name' => PlainText::required($translations[$languageCode] ?? null, 160, squish: true)],
            );
        }
    }
}
