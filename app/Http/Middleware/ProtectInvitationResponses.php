<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProtectInvitationResponses
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return self::protect($request, $next($request));
    }

    public static function protect(Request $request, Response $response): Response
    {
        if ($request->hasHeader('X-Livewire') || $request->routeIs(
            'login', 'login.store', 'logout', 'password.*', 'two-factor.login',
            'two-factor.login.store', 'verification.*', 'invitations.*', 'demo-login.*',
        ) || Str::is([
            'invite', 'invite/*', 'login', 'demo-login', 'demo-login/*',
            'local-login/*', 'forgot-password', 'reset-password', 'reset-password/*',
            'two-factor-challenge', 'user/confirm-password', 'email/verify',
        ], $request->decodedPath())) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
