<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

final class UpdateUserPasswordAction
{
    public function handle(User $user, string $password): User
    {
        $user->updateOrFail(['password' => $password]);

        return $user;
    }
}
