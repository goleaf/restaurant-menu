<?php

use App\Exceptions\BusinessRuleViolation;
use App\Http\Middleware\EnsureDemoLoginIsEnabled;
use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Http\Middleware\SetInterfaceLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetInterfaceLocale::class,
        ]);

        $middleware->alias([
            'demo-login' => EnsureDemoLoginIsEnabled::class,
            'superadmin' => EnsureUserIsSuperadmin::class,
        ]);

        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
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
        $exceptions->context(function (): array {
            $request = request();
            $route = $request->route();

            return [
                'http_method' => $request->method(),
                'request_path' => $request->path(),
                'route_name' => $route?->getName(),
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
    })->create();
