<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;

final class StartSessionWithoutCredentialHistory extends StartSession
{
    public function __construct(SessionManager $manager, Container $container)
    {
        parent::__construct($manager, fn (): CacheFactory => $container->make(CacheFactory::class));
    }

    /**
     * @param  Session  $session
     */
    protected function storeCurrentUrl(Request $request, $session): void
    {
        if ($request->routeIs('invitations.show', 'password.reset')) {
            return;
        }

        parent::storeCurrentUrl($request, $session);
    }
}
