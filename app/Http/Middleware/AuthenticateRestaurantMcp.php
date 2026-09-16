<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Models\McpAccessToken;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\RequestGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AuthenticateRestaurantMcp
{
    public function __construct(private readonly McpAccess $access) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $this->handleRequest($request, $next);
        } catch (AuthenticationException) {
            return $this->failure(401);
        } catch (AuthorizationException) {
            return $this->failure(403);
        } catch (Throwable $exception) {
            report(new RuntimeException('MCP request failed ('.class_basename($exception).').'));

            return $this->failure(500);
        }
    }

    /** @param Closure(Request): Response $next */
    private function handleRequest(Request $request, Closure $next): Response
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
        if (count($request->headers->all('Origin')) > 1
            || ($origin !== null && $origin !== $expectedOrigin)
            || (app()->isProduction() && ! $request->isSecure())) {
            return $this->failure(403);
        }
        $limitKey = 'restaurant-mcp:ip:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($limitKey, (int) config('restaurant-mcp.requests_per_minute', 120))) {
            return $this->failure(429)->withHeaders(['Retry-After' => (string) RateLimiter::availableIn($limitKey)]);
        }
        RateLimiter::hit($limitKey, 60);
        $authorization = $request->header('Authorization');
        if (count($request->headers->all('Authorization')) !== 1
            || ! is_string($authorization)
            || preg_match('/\A(?i:Bearer) (rm_mcp_[a-f0-9]{64})\z/', $authorization, $matches) !== 1) {
            return $this->failure(401);
        }
        $tokenId = McpAccessToken::query()->usable()->where('token_hash', hash('sha256', $matches[1]))->value('id');
        if (! is_int($tokenId)) {
            return $this->failure(401);
        }
        $context = $this->access->resolve($tokenId);
        $previousGuard = Auth::getDefaultDriver();
        $previousLocale = app()->getLocale();
        $request->attributes->set(McpContext::class, $context);
        /** @var RequestGuard $guard */
        $guard = Auth::guard('restaurant-mcp');
        $guard->setRequest($request)->forgetUser();
        Auth::shouldUse('restaurant-mcp');
        app()->setLocale($context->user->preferredLocale());
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            return $response;
        } finally {
            $request->attributes->remove(McpContext::class);
            $guard->forgetUser();
            Auth::shouldUse($previousGuard);
            app()->setLocale($previousLocale);
        }
    }

    private function failure(int $status): JsonResponse
    {
        $response = response()->json(['message' => __('mcp.errors.request_denied')], $status)
            ->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
        if ($status === 401) {
            $response->headers->set('WWW-Authenticate', 'Bearer realm="restaurant-mcp"');
        }

        return $response;
    }
}
