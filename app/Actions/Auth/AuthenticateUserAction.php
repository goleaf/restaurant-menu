<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Support\Auth\AuthRequestAdapter;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateUserAction
{
    public function __construct(private readonly AuthRequestAdapter $adapter, private readonly StatefulGuard $guard, private readonly Pipeline $pipeline, private readonly LoginResponse $response) {}

    /** @param array{email: string, password: string, remember: bool} $input */
    public function handle(Request $source, array $input): Response
    {
        abort_if($this->guard->check(), 403);
        $request = $this->adapter->request($source, [
            Fortify::username() => $input['email'], 'password' => $input['password'], 'remember' => $input['remember'],
        ], LoginRequest::class);
        $authenticate = function (Request $request): Response {
            $pipes = match (true) {
                Fortify::$authenticateThroughCallback !== null => call_user_func(Fortify::$authenticateThroughCallback, $request),
                is_array(config('fortify.pipelines.login')) => config('fortify.pipelines.login'),
                default => [
                    config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
                    config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
                    Features::enabled(Features::twoFactorAuthentication()) ? RedirectsIfTwoFactorAuthenticatable::class : null,
                    AttemptToAuthenticate::class,
                    PrepareAuthenticatedSession::class,
                ],
            };
            $result = $this->pipeline->send($request)->through(array_filter($pipes))
                ->then(fn (Request $request) => $this->response);

            return $this->adapter->response($result, $request);
        };
        $limiter = config('fortify.limiters.login');

        return is_string($limiter) && $limiter !== ''
            ? $this->adapter->throttle($request, $authenticate, $limiter)
            : $authenticate($request);
    }
}
