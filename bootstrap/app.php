<?php

use App\Actions\Monitoring\ReportProductionExceptionAction;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureDemoLoginIsEnabled;
use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Http\Middleware\RequireJsonHealthCheckResponse;
use App\Http\Middleware\SetInterfaceLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequireJsonHealthCheckResponse::class);
        $middleware->append(AssignRequestId::class);

        $middleware->web(append: [
            SetInterfaceLocale::class,
        ]);

        $middleware->alias([
            'demo-login' => EnsureDemoLoginIsEnabled::class,
            'superadmin' => EnsureUserIsSuperadmin::class,
        ]);

        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: PreventRequestForgery::class,
        );
        $middleware->prependToPriorityList(
            before: PreventRequestForgery::class,
            prepend: EnsureDemoLoginIsEnabled::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
        $exceptions->dontReport([
            BusinessRuleViolation::class,
        ]);
        $exceptions->report(function (Throwable $exception): void {
            app(ReportProductionExceptionAction::class)->handle($exception);
        });
        $exceptions->context(function (): array {
            $request = request();
            $route = $request->route();

            return [
                'http_method' => $request->method(),
                'request_id' => $request->attributes->get('request_id'),
                'route_name' => $route?->getName(),
                'route_uri' => $route?->uri(),
                'guest_surface' => $request->is('q/*') || $request->is('guest*'),
            ];
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (BusinessRuleViolation $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->errorType()->message(),
                'error' => [
                    'type' => $exception->errorType()->value,
                    'code' => $exception->businessRule()->value,
                ],
                'errors' => $exception->errors(),
            ], $exception->errorType()->statusCode());
        });
        $exceptions->respond(function (Response $response): Response {
            $request = request();
            $requestId = $request->attributes->get('request_id');

            if (is_string($requestId) && $requestId !== '') {
                $response->headers->set('X-Request-Id', $requestId);
            }

            if ($request->is('up')) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            }

            return $response;
        });
    })->create();
