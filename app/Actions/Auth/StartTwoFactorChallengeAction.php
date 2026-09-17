<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Support\Auth\TwoFactorChallengeContext;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;

final class StartTwoFactorChallengeAction implements RedirectsIfTwoFactorAuthenticatable
{
    public function __construct(private readonly RedirectIfTwoFactorAuthenticatable $fortify, private readonly TwoFactorChallengeContext $context) {}

    public function handle($request, $next): mixed
    {
        $this->context->clear($request);
        $response = $this->fortify->handle($request, $next);
        if ($request->session()->has('login.id')) {
            $this->context->start($request);
        }

        return $response;
    }
}
