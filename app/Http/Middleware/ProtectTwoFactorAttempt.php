<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\TwoFactorChallengeContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProtectTwoFactorAttempt
{
    public function __construct(private readonly TwoFactorChallengeContext $context) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('two-factor.login.store')) {
            return $next($request);
        }

        return $this->context->run($request, fn (): Response => $next($request));
    }
}
