<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\Auth\DemoRoleUserQuery;
use App\Support\Auth\InteractiveLoginGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginAsDemoRoleAction
{
    public function __construct(private readonly InteractiveLoginGuard $guard, private readonly DemoRoleUserQuery $users) {}

    public function handle(Request $request, SystemRole $role): RedirectResponse
    {
        $this->guard->authorize($request, local: false);
        $user = $this->users->find($role);
        if (! $user instanceof User) {
            throw ValidationException::withMessages(['form.role' => __('demo_login.unavailable_error')]);
        }
        $request->session()->forget(['auth.password_confirmed_at', 'login.id', 'login.remember', 'url.intended']);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new RedirectResponse(route('dashboard'));
    }
}
