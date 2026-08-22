<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAsDemoRoleAction;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LoginAsDemoRoleController extends Controller
{
    public function __invoke(
        Request $request,
        SystemRole $role,
        LoginAsDemoRoleAction $loginAsDemoRole,
    ): RedirectResponse {
        if (! $loginAsDemoRole->handle($request, $role)) {
            return to_route('demo-login.index')
                ->withErrors(['demo_login' => __('demo_login.unavailable_error')]);
        }

        return to_route('dashboard');
    }
}
