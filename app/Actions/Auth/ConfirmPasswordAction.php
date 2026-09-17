<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Support\Auth\AuthRequestAdapter;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmPassword;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse;
use Symfony\Component\HttpFoundation\Response;

final class ConfirmPasswordAction
{
    public function __construct(private readonly StatefulGuard $guard, private readonly AuthRequestAdapter $adapter, private readonly ConfirmPassword $confirm, private readonly PasswordConfirmedResponse $response) {}

    public function handle(Request $source, string $password): Response
    {
        $user = $this->guard->user();
        abort_if($user === null, 401);
        if (! ($this->confirm)($this->guard, $user, $password)) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }
        $source->session()->put('auth.password_confirmed_at', now()->timestamp);

        return $this->adapter->response($this->response, $this->adapter->request($source, []));
    }
}
