<?php

declare(strict_types=1);

namespace Tests\Support;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Composer\InstalledVersions;
use Pest\Browser\Drivers\LaravelHttpServer;
use Pest\Browser\ServerManager;
use Psr\Log\NullLogger;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use WeakMap;

/**
 * Pest Browser 4.3.1 privately constructs Amp HTTP 3.4.6 with a 128 KiB body limit.
 * Configure that same isolated test socket before bootstrap so a full SQLite fixture can reach Laravel.
 * Only the transport budget changes; signed uploads, CSRF and application file rules stay active.
 */
final class PestFileUploadServer
{
    private static ?WeakMap $configured = null;

    public static function configure(): void
    {
        if (InstalledVersions::getPrettyVersion('pestphp/pest-plugin-browser') !== 'v4.3.1'
            || InstalledVersions::getPrettyVersion('amphp/http-server') !== 'v3.4.6') {
            throw new RuntimeException('Review the scoped file-upload test socket adapter for the installed Pest/Amp versions.');
        }
        $server = ServerManager::instance()->http();
        if (! $server instanceof LaravelHttpServer || $server->host !== '127.0.0.1') {
            throw new RuntimeException('File-upload browser tests require their existing loopback Laravel server.');
        }
        self::$configured ??= new WeakMap;
        $property = new ReflectionProperty($server, 'socket');
        if ($property->getValue($server) !== null) {
            if (isset(self::$configured[$server])) {
                return;
            }
            throw new RuntimeException('Configure the scoped upload body limit before browser bootstrap.');
        }
        $logger = new NullLogger;
        $socket = SocketHttpServer::createForDirectAccess($logger, httpDriverFactory: new DefaultHttpDriverFactory($logger, bodySizeLimit: 2 * 1024 * 1024));
        $socket->expose($server->host.':'.$server->port);
        $handler = (new ReflectionMethod($server, 'handleRequest'))->getClosure($server);
        $socket->start(new ClosureRequestHandler($handler), new DefaultErrorHandler);
        $property->setValue($server, $socket);
        self::$configured[$server] = true;
    }
}
