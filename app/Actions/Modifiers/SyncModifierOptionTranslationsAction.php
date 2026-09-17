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
        $option->loadMissing('translations');
        $existing = $option->translations->keyBy('language_code');
        foreach (SupportedLocale::values() as $languageCode) {
            $translation = $existing->get($languageCode) ?? $option->translations()->make(['language_code' => $languageCode]);
            $translation->name = PlainText::required($translations[$languageCode] ?? null, 160, squish: true);
            if ($translation->save() !== true) {
                throw new \RuntimeException('The modifier translation could not be saved.');
            }
        }
    }
}
