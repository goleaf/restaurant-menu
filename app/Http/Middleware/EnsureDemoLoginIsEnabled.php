<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDemoLoginIsEnabled
{
    public function __construct(private readonly Application $application) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->application->isProduction() || config('demo-login.enabled') !== true) {
            abort(404);
        }

        return $next($request);
    }
}
