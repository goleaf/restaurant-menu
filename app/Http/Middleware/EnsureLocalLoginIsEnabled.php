<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\DemoLogin\DemoEnvironment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureLocalLoginIsEnabled
{
    public function __construct(private readonly DemoEnvironment $environment) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->environment->allowsLocalRequest($request), 404);

        return $next($request);
    }
}
