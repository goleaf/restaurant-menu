<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureSharedHostingInfrastructure();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Keep infrastructure project-local for shared hosting.
     */
    protected function configureSharedHostingInfrastructure(): void
    {
        $allowedCacheStores = ['array', 'database', 'file', 'null'];
        $allowedQueueConnections = ['sync', 'database', 'deferred', 'background', 'null'];
        $allowedSessionDrivers = ['file', 'cookie', 'database', 'array'];

        $cacheDefault = config('cache.default');
        $queueDefault = config('queue.default');
        $sessionDriver = config('session.driver');

        config()->set([
            'database.default' => 'sqlite',
            'database.connections' => [
                'sqlite' => config('database.connections.sqlite'),
            ],
            'database.redis' => null,
            'cache.default' => in_array($cacheDefault, $allowedCacheStores, true) ? $cacheDefault : 'database',
            'cache.stores' => [
                'array' => config('cache.stores.array'),
                'database' => config('cache.stores.database'),
                'file' => config('cache.stores.file'),
                'null' => ['driver' => 'null'],
            ],
            'queue.default' => in_array($queueDefault, $allowedQueueConnections, true) ? $queueDefault : 'database',
            'queue.connections' => [
                'sync' => config('queue.connections.sync'),
                'database' => config('queue.connections.database'),
                'deferred' => config('queue.connections.deferred'),
                'background' => config('queue.connections.background'),
                'null' => ['driver' => 'null'],
            ],
            'session.driver' => in_array($sessionDriver, $allowedSessionDrivers, true) ? $sessionDriver : 'database',
            'mail.mailers' => [
                'smtp' => config('mail.mailers.smtp'),
                'sendmail' => config('mail.mailers.sendmail'),
                'log' => config('mail.mailers.log'),
                'array' => config('mail.mailers.array'),
            ],
            'logging.channels' => [
                'stack' => config('logging.channels.stack'),
                'single' => config('logging.channels.single'),
                'daily' => config('logging.channels.daily'),
                'stderr' => config('logging.channels.stderr'),
                'syslog' => config('logging.channels.syslog'),
                'errorlog' => config('logging.channels.errorlog'),
                'null' => config('logging.channels.null'),
                'emergency' => config('logging.channels.emergency'),
            ],
        ]);
    }
}
