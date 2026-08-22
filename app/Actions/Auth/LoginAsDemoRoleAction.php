<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\SystemRole;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LoginAsDemoRoleAction
{
    public function handle(Request $request, SystemRole $role): bool
    {
        $identity = DemoAccountCatalog::forRole($role);
        $user = User::query()
            ->select(['id', 'email'])
            ->with('roles:id,code')
            ->where('email', $identity['email'])
            ->first();

        if (! $user instanceof User || ! $user->hasSystemRole($role)) {
            return false;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return true;
    }
}
