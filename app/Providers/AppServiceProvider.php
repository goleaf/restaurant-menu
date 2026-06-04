<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Models\KitchenDepartment;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Observers\KitchenDepartmentObserver;
use App\Observers\ManualPaymentObserver;
use App\Observers\MenuCategoryObserver;
use App\Observers\MenuCategoryTranslationObserver;
use App\Observers\MenuItemObserver;
use App\Observers\MenuItemTranslationObserver;
use App\Observers\MenuObserver;
use App\Observers\ModifierGroupObserver;
use App\Observers\ModifierOptionObserver;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\TableSessionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

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
        $this->registerModelObservers();

        Livewire::addPersistentMiddleware([
            EnsureUserIsSuperadmin::class,
        ]);
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

    protected function registerModelObservers(): void
    {
        Menu::observe(MenuObserver::class);
        MenuCategory::observe(MenuCategoryObserver::class);
        MenuCategoryTranslation::observe(MenuCategoryTranslationObserver::class);
        MenuItem::observe(MenuItemObserver::class);
        MenuItemTranslation::observe(MenuItemTranslationObserver::class);
        KitchenDepartment::observe(KitchenDepartmentObserver::class);
        ModifierGroup::observe(ModifierGroupObserver::class);
        ModifierOption::observe(ModifierOptionObserver::class);
        Order::observe(OrderObserver::class);
        OrderItem::observe(OrderItemObserver::class);
        ManualPayment::observe(ManualPaymentObserver::class);
        TableSession::observe(TableSessionObserver::class);
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
