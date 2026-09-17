<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Support\DemoLogin\DemoEnvironment;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class InteractiveLoginGuard
{
    public function __construct(private readonly DemoEnvironment $environment) {}

    public function allowsLocal(Request $request): bool
    {
        return Auth::guard('web')->guest() && $this->environment->allowsLocalRequest($request);
    }

    public function authorize(Request $request, bool $local): void
    {
        abort_unless($local ? $this->environment->allowsLocalRequest($request) : $this->environment->allowsRequest($request), 404);
        abort_unless(Auth::guard('web')->guest(), 403);
    }

    /** @param Closure(Request): Response $operation */
    public function run(Request $request, bool $local, Closure $operation): Response
    {
        $this->authorize($request, $local);

        return app(AuthRequestAdapter::class)->throttle($request, $operation, 'demo-login');
    }
}
