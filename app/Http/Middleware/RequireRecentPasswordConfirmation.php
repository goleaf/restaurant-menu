<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRecentPasswordConfirmation
{
    public function __construct(private readonly RequirePassword $requirePassword) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        ?string $redirectToRoute = null,
        string|int|null $passwordTimeoutSeconds = null,
    ): Response {
        $response = $this->requirePassword->handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);

        if ($response->getStatusCode() === 423) {
            abort($response);
        }

        return $response;
    }
}
