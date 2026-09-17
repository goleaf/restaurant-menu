<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Auth\InteractiveLoginGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LoginAsLocalUserAction
{
    public function __construct(private readonly InteractiveLoginGuard $guard) {}

    public function handle(Request $request, int $userId): RedirectResponse
    {
        $this->guard->authorize($request, local: true);
        $user = User::query()->select(['id', 'email', 'password', 'remember_token'])->findOrFail($userId);
        $request->session()->forget(['auth.password_confirmed_at', 'login.id', 'login.remember', 'url.intended']);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new RedirectResponse(route('dashboard'));
    }
}
