<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

final class UpdateUserProfileAction
{
    /** @param array{name: string, email: string, locale: string} $data */
    public function handle(User $user, array $data): User
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->saveOrFail();

        return $user;
    }
}
