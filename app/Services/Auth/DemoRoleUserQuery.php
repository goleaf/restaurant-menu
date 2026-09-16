<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\SystemRole;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;

final class DemoRoleUserQuery
{
    public function find(SystemRole $role): ?User
    {
        $identity = DemoAccountCatalog::forRole($role);
        $user = User::query()
            ->select(['id', 'email'])
            ->with('roles:id,code')
            ->where('email', $identity['email'])
            ->first();

        if (! $user instanceof User || ! $user->hasSystemRole($role)) {
            return null;
        }

        return $user;
    }
}
