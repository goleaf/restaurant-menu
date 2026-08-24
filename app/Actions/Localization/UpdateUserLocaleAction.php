<?php

declare(strict_types=1);

namespace App\Actions\Localization;

use App\Enums\SupportedLocale;
use App\Models\User;

final class UpdateUserLocaleAction
{
    public function handle(User $user, string $locale): User
    {
        $locale = SupportedLocale::normalize($locale);

        if ($user->locale !== $locale) {
            $user->fill(['locale' => $locale])->saveOrFail();
        }

        return $user;
    }
}
