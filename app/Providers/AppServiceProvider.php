<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use App\Models\MenuTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierGroupTranslation;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTranslation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\TableSession;
use App\Observers\BranchObserver;
use App\Observers\BranchSettingObserver;
use App\Observers\BrandObserver;
use App\Observers\DraftOrderObserver;
use App\Observers\KitchenDepartmentObserver;
use App\Observers\KitchenTicketItemObserver;
use App\Observers\KitchenTicketObserver;
use App\Observers\ManualPaymentObserver;
use App\Observers\MenuAvailabilityScheduleObserver;
use App\Observers\MenuCategoryObserver;
use App\Observers\MenuCategoryTranslationObserver;
use App\Observers\MenuItemObserver;
use App\Observers\MenuItemTranslationObserver;
use App\Observers\MenuItemVariantObserver;
use App\Observers\MenuItemVariantTranslationObserver;
use App\Observers\MenuObserver;
use App\Observers\MenuTranslationObserver;
use App\Observers\ModifierGroupObserver;
use App\Observers\ModifierGroupTranslationObserver;
use App\Observers\ModifierOptionObserver;
use App\Observers\ModifierOptionTranslationObserver;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\OrganizationObserver;
use App\Observers\TableSessionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
        $this->registerModelObservers();

        Livewire::addPersistentMiddleware([
            EnsureUserIsSuperadmin::class,
        ]);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for(
            'demo-login',
            fn (Request $request): Limit => Limit::perMinute(20)->by((string) $request->ip()),
        );

        RateLimiter::for('public-qr', function (Request $request): array {
            $token = (string) $request->route('token');
            $clientAddress = (string) $request->ip();

            return [
                Limit::perMinute(120)->by('ip|'.$clientAddress),
                Limit::perMinute(30)->by('qr|'.hash('sha256', $token).'|'.$clientAddress),
            ];
        });

        RateLimiter::for('staff-invitations', function (Request $request): array {
            $token = (string) $request->route('token');
            $invitationId = $request->session()->get('staff_invitation_id');
            $credentialScope = $token !== ''
                ? $token
                : (is_int($invitationId) ? 'invitation:'.$invitationId : 'missing-invitation');
            $clientAddress = (string) $request->ip();

            return [
                Limit::perMinute(30)->by('ip|'.$clientAddress),
                Limit::perMinute(10)->by('credential|'.hash('sha256', $credentialScope).'|'.$clientAddress),
            ];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            $this->app->isProduction(),
        );

        Password::defaults(fn (): ?Password => $this->app->isProduction()
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
        Organization::observe(OrganizationObserver::class);
        Brand::observe(BrandObserver::class);
        Branch::observe(BranchObserver::class);
        BranchSetting::observe(BranchSettingObserver::class);
        Menu::observe(MenuObserver::class);
        MenuTranslation::observe(MenuTranslationObserver::class);
        MenuAvailabilitySchedule::observe(MenuAvailabilityScheduleObserver::class);
        MenuCategory::observe(MenuCategoryObserver::class);
        MenuCategoryTranslation::observe(MenuCategoryTranslationObserver::class);
        MenuItem::observe(MenuItemObserver::class);
        MenuItemTranslation::observe(MenuItemTranslationObserver::class);
        MenuItemVariant::observe(MenuItemVariantObserver::class);
        MenuItemVariantTranslation::observe(MenuItemVariantTranslationObserver::class);
        KitchenDepartment::observe(KitchenDepartmentObserver::class);
        ModifierGroup::observe(ModifierGroupObserver::class);
        ModifierGroupTranslation::observe(ModifierGroupTranslationObserver::class);
        ModifierOption::observe(ModifierOptionObserver::class);
        ModifierOptionTranslation::observe(ModifierOptionTranslationObserver::class);
        Order::observe(OrderObserver::class);
        OrderItem::observe(OrderItemObserver::class);
        ManualPayment::observe(ManualPaymentObserver::class);
        TableSession::observe(TableSessionObserver::class);
        DraftOrder::observe(DraftOrderObserver::class);
        KitchenTicket::observe(KitchenTicketObserver::class);
        KitchenTicketItem::observe(KitchenTicketItemObserver::class);
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
            'filesystems.default' => 'public',
            'filesystems.disks' => [
                'local' => config('filesystems.disks.local'),
                'public' => config('filesystems.disks.public'),
            ],
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
                'deprecations' => config('logging.channels.deprecations'),
                'stderr' => config('logging.channels.stderr'),
                'syslog' => config('logging.channels.syslog'),
                'errorlog' => config('logging.channels.errorlog'),
                'null' => config('logging.channels.null'),
                'emergency' => config('logging.channels.emergency'),
            ],
        ]);
    }
}
