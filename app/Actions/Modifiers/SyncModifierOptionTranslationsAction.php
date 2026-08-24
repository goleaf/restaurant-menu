<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Enums\SupportedLocale;
use App\Models\ModifierOption;
use App\Support\PlainText;

class SyncModifierOptionTranslationsAction
{
    /** @param array<string, string|null> $translations */
    public function handle(ModifierOption $option, array $translations): void
    {
        foreach (SupportedLocale::values() as $languageCode) {
            $option->translations()->updateOrCreate(
                ['language_code' => $languageCode],
                ['name' => PlainText::required($translations[$languageCode] ?? null, 160, squish: true)],
            );
        }
    }
}
