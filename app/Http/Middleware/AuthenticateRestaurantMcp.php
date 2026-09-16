<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SupportedLocale;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Models\McpAccessToken;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateRestaurantMcp
{
    public function __construct(private readonly McpAccess $access) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('restaurant-mcp.enabled')) {
            return $this->failure(404);
        }
        if (strlen((string) $request->getContent()) > (int) config('restaurant-mcp.max_request_bytes', 65536)) {
            return $this->failure(413);
        }
        $origin = $request->header('Origin');
        $applicationUrl = parse_url((string) config('app.url'));
        $expectedOrigin = is_array($applicationUrl) && isset($applicationUrl['scheme'], $applicationUrl['host'])
            ? $applicationUrl['scheme'].'://'.$applicationUrl['host'].(isset($applicationUrl['port']) ? ':'.$applicationUrl['port'] : '') : '';
        if (($origin !== null && $origin !== $expectedOrigin) || (app()->isProduction() && ! $request->isSecure())) {
            return $this->failure(403);
        }
        $limitKey = 'restaurant-mcp:ip:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($limitKey, (int) config('restaurant-mcp.requests_per_minute', 120))) {
            return $this->failure(429)->withHeaders(['Retry-After' => (string) RateLimiter::availableIn($limitKey)]);
        }
        RateLimiter::hit($limitKey, 60);
        $bearer = $request->bearerToken();
        if (! is_string($bearer) || preg_match('/\Arm_mcp_[a-f0-9]{64}\z/', $bearer) !== 1) {
            return $this->failure(401);
        }
        $tokenId = McpAccessToken::query()->usable()->where('token_hash', hash('sha256', $bearer))->value('id');
        if (! is_int($tokenId)) {
            return $this->failure(401);
        }
        try {
            $context = $this->access->resolve($tokenId);
        } catch (AuthenticationException) {
            return $this->failure(401);
        } catch (AuthorizationException) {
            return $this->failure(403);
        }
        $previousGuard = Auth::getDefaultDriver();
        $previousLocale = app()->getLocale();
        $request->attributes->set(McpContext::class, $context);
        Auth::shouldUse('restaurant-mcp');
        app()->setLocale(SupportedLocale::tryFrom((string) $context->user->locale)?->value ?? 'en');
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            return $response;
        } finally {
            $request->attributes->remove(McpContext::class);
            Auth::forgetGuards();
            Auth::shouldUse($previousGuard);
            app()->setLocale($previousLocale);
        }
    }

    private function failure(int $status): \Illuminate\Http\JsonResponse
    {
        $response = response()->json(['message' => __('mcp.errors.request_denied')], $status)
            ->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
        if ($status === 401) {
            $response->headers->set('WWW-Authenticate', 'Bearer realm="restaurant-mcp"');
        }

        return $response;
    }
}
