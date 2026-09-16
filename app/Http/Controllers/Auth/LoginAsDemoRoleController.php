<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\DemoRoleUserQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LoginAsDemoRoleController extends Controller
{
    public function __invoke(
        Request $request,
        SystemRole $role,
        DemoRoleUserQuery $demoRoleUsers,
    ): RedirectResponse {
        $user = $demoRoleUsers->find($role);

        if (! $user instanceof User) {
            return to_route('demo-login.index')
                ->withErrors(['demo_login' => __('demo_login.unavailable_error')]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
