<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IsolatedBrowserIdentity
{
    public static function configure(): void
    {
        $directory = sys_get_temp_dir().'/restaurant-browser-identity-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700, true)) {
            throw new \RuntimeException('Cannot create isolated browser session storage.');
        }
        register_shutdown_function(static function () use ($directory): void {
            (new Filesystem)->deleteDirectory($directory);
        });
        config()->set('session.driver', 'file');
        config()->set('session.files', $directory);
        app('router')->prependMiddlewareToGroup('web', self::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Pest reuses its Laravel container between HTTP requests; PHP-FPM does not.
        app('auth')->forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        app('redirect')->setSession(app('session')->driver());

        return $next($request);
    }
}
