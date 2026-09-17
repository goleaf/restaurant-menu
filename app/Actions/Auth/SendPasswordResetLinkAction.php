<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

final class SendPasswordResetLinkAction
{
    public function __construct(private readonly StatefulGuard $guard) {}

    public function handle(string $email): void
    {
        abort_unless(Features::enabled(Features::resetPasswords()), 404);
        abort_if($this->guard->check(), 403);
        Password::broker(config('fortify.passwords'))->sendResetLink([
            Fortify::email() => config('fortify.lowercase_usernames') ? Str::lower($email) : $email,
        ]);
    }
}
