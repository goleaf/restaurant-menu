<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Support\Auth\AuthRequestAdapter;
use App\Support\Auth\PasswordResetContext;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\CompletePasswordReset;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

final class ResetPasswordAction
{
    public function __construct(private readonly PasswordResetContext $context, private readonly AuthRequestAdapter $adapter, private readonly StatefulGuard $guard, private readonly ResetsUserPasswords $resetter, private readonly CompletePasswordReset $complete) {}

    /** @param array{email: string, password: string, password_confirmation: string} $input */
    public function handle(Request $source, array $input, string $contextId): Response
    {
        abort_unless(Features::enabled(Features::resetPasswords()), 404);
        abort_if($this->guard->check(), 403);
        $token = $this->context->token($source);
        $email = config('fortify.lowercase_usernames') ? Str::lower($input['email']) : $input['email'];
        $contextEmail = $this->context->email($source);
        $contextEmail = config('fortify.lowercase_usernames') ? Str::lower($contextEmail) : $contextEmail;
        if ($token === null || ! hash_equals($this->context->identifier($source) ?? '', $contextId)
            || $contextId === '' || ! hash_equals($contextEmail, $email)) {
            throw ValidationException::withMessages(['email' => __('passwords.token')]);
        }
        $input = [
            Fortify::email() => $email,
            'password' => $input['password'], 'password_confirmation' => $input['password_confirmation'], 'token' => $token,
        ];
        $status = Password::broker(config('fortify.passwords'))->reset($input, function ($user) use ($input): void {
            $this->resetter->reset($user, $input);
            ($this->complete)($this->guard, $user);
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('passwords.token')]);
        }
        $this->context->clear($source);
        $source->session()->flash('status', __('passwords.reset'));

        return $this->adapter->passwordResetResponse($status, $this->adapter->request($source, []));
    }
}
