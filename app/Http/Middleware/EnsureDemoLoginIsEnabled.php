<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\DemoLogin\DemoEnvironment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDemoLoginIsEnabled
{
    public function __construct(private readonly DemoEnvironment $demoEnvironment) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->demoEnvironment->allowsRequest($request)) {
            abort(404);
        }

        return $next($request);
    }
}
