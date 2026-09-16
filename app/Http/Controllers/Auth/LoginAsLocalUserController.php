<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LoginAsLocalUserController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget(['auth.password_confirmed_at', 'login.id', 'login.remember']);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return to_route('dashboard')->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
