<?php

declare(strict_types=1);

namespace App\Actions\Localization;

use App\Enums\SupportedLocale;
use App\Models\TableSessionGuest;

final class UpdateGuestLocaleAction
{
    public function handle(TableSessionGuest $guest, string $locale): TableSessionGuest
    {
        $locale = SupportedLocale::normalize($locale);

        if ($guest->locale !== $locale) {
            $guest->fill(['locale' => $locale])->saveOrFail();
        }

        return $guest;
    }
}
