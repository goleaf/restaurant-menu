<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Support\Auth\AuthRequestAdapter;
use App\Support\Auth\TwoFactorChallengeContext;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Symfony\Component\HttpFoundation\Response;

final class CompleteTwoFactorLoginAction
{
    public function __construct(private readonly AuthRequestAdapter $adapter, private readonly StatefulGuard $guard, private readonly TwoFactorChallengeContext $context, private readonly FailedTwoFactorLoginResponse $failedResponse, private readonly TwoFactorLoginResponse $successResponse) {}

    /** @param array{code: ?string, recovery_code: ?string} $input */
    public function handle(Request $source, array $input, string $challengeId): Response
    {
        abort_unless(Features::enabled(Features::twoFactorAuthentication()), 404);
        abort_if($this->guard->check(), 403);
        if ($challengeId === '' || ! hash_equals($this->context->identifier($source) ?? '', $challengeId)) {
            return new RedirectResponse(route('login'));
        }
        $request = $this->adapter->request($source, $input, TwoFactorLoginRequest::class);
        $authenticate = function (TwoFactorLoginRequest $request): Response {
            if (! $request->hasChallengedUser()) {
                return new RedirectResponse(route('login'));
            }
            $user = DB::transaction(function () use ($request) {
                $user = $request->challengedUser();
                if ($code = $request->validRecoveryCode()) {
                    $this->context->consume($request);
                    $user->replaceRecoveryCode($code);
                } elseif (! $request->hasValidCode()) {
                    event(new TwoFactorAuthenticationFailed($user));
                    $request->headers->set('Accept', 'application/json');

                    return $this->adapter->response($this->failedResponse, $request);
                } else {
                    $this->context->consume($request);
                }

                return $user;
            });
            if ($user instanceof Response) {
                return $user;
            }
            event(new ValidTwoFactorAuthenticationCodeProvided($user));
            $this->guard->login($user, $request->remember());
            $request->session()->regenerate();

            return $this->adapter->response($this->successResponse, $request);
        };
        $limiter = config('fortify.limiters.two-factor');

        return $this->context->run($request, fn (): Response => is_string($limiter) && $limiter !== ''
            ? $this->adapter->throttle($request, $authenticate, $limiter)
            : $authenticate($request));
    }
}
