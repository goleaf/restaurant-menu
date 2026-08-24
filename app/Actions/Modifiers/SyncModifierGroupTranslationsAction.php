<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Enums\SupportedLocale;
use App\Models\ModifierGroup;
use App\Support\PlainText;

class SyncModifierGroupTranslationsAction
{
    /** @param array<string, string|null> $translations */
    public function handle(ModifierGroup $group, array $translations): void
    {
        foreach (SupportedLocale::values() as $languageCode) {
            $group->translations()->updateOrCreate(
                ['language_code' => $languageCode],
                ['name' => PlainText::required($translations[$languageCode] ?? null, 160, squish: true)],
            );
        }
    }
}
