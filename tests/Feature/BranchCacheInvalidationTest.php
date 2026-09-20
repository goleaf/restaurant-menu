<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\Branches\SaveBranchSettingsGroupAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DatabaseCacheEntry;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Support\Branches\BranchSettingsGroup;
use App\Support\BranchReportCacheVersion;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('branch cache invalidation replaces the generation and batch deletes scoped payloads', function (): void {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $cache = prompt93BranchCache();
    $cache->getStore()->setPrefix('branch-batch_%:');
    $keys = ForgetBranchCacheAction::cacheKeysForBranch($branch->id);
    foreach ($keys as $key) {
        $cache->put($key, 'old value', 60);
        $cache->put(CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX.$key, now()->timestamp, 60);
    }
    $neighborKey = GetGuestMenuForBranchAction::cacheKey($otherBranch->id, 'en');
    $cache->put($neighborKey, 'other branch', 60);
    $cache->put('unrelated:key', 'unrelated', 60);
    $foreign = DatabaseCacheEntry::factory()->create(['key' => 'another-app:'.$keys[0]]);

    $generation = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id]));
    $otherGeneration = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$otherBranch->id]));
    $queries = countDatabaseQueries(fn () => app(ForgetBranchCacheAction::class)->handle($branch->id));

    expect($queries)->toBe(2)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id])))->not->toBe($generation)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$otherBranch->id])))->toBe($otherGeneration);
    foreach ($keys as $key) {
        expect($cache->has($key))->toBeFalse()
            ->and($cache->has(CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX.$key))->toBeFalse();
    }
    expect($cache->get($neighborKey))->toBe('other branch')
        ->and($cache->get('unrelated:key'))->toBe('unrelated')
        ->and($foreign->fresh())->not->toBeNull();
});

test('branch invalidation keeps non database store support', function (string $driver): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'branch-cache-fallback-'.getmypid());
    Storage::fake('local');
    config()->set('cache.stores.database', [
        'driver' => $driver,
        'path' => Storage::disk('local')->path('branch-cache'),
        'serialize' => false,
    ]);
    Cache::purge('database');
    $cache = prompt93BranchCache();
    $keys = ForgetBranchCacheAction::cacheKeysForBranch(17);
    foreach ($keys as $key) {
        $cache->put($key, 'old value', 60);
    }
    $cache->put(GetGuestMenuForBranchAction::cacheKey(18, 'en'), 'other branch', 60);

    expect(countDatabaseQueries(fn () => app(ForgetBranchCacheAction::class)->handle(17)))->toBe(0);

    foreach ($keys as $key) {
        expect($cache->has($key))->toBeFalse();
    }
    expect($cache->get(GetGuestMenuForBranchAction::cacheKey(18, 'en')))->toBe('other branch');
})->with(['array', 'file']);

test('branch invalidation preserves per key cache events when a listener is registered', function (bool $wildcard): void {
    $cache = prompt93BranchCache();
    $keys = ForgetBranchCacheAction::cacheKeysForBranch(17);
    foreach ($keys as $key) {
        $cache->put($key, 'old value', 60);
    }
    $forgotten = [];
    $listener = function (KeyForgotten $event) use (&$forgotten, $cache): void {
        $forgotten[] = $event->key;
        expect($event->storeName)->toBe('database')->and($cache->has($event->key))->toBeFalse();
    };
    if ($wildcard) {
        Event::listen('Illuminate\\Cache\\Events\\*', function (string $eventName, array $payload) use ($listener): void {
            if ($eventName === KeyForgotten::class) {
                $listener($payload[0]);
            }
        });
    } else {
        Event::listen(KeyForgotten::class, $listener);
    }

    app(ForgetBranchCacheAction::class)->handle(17);

    expect($forgotten)->toBe(['report-version:v1:guest-menu:branch:17', ...$keys]);
})->with(['specific' => false, 'wildcard' => true]);

test('branch invalidation preserves overridden database store forget behavior', function (): void {
    $store = new class(DB::connection(), 'cache', 'custom-branch-cache:') extends DatabaseStore
    {
        public int $forgotten = 0;

        public function forget($key): bool
        {
            $this->forgotten++;

            return parent::forget($key);
        }
    };
    $cache = prompt93BranchCache();
    $cache->setStore($store);
    $keys = ForgetBranchCacheAction::cacheKeysForBranch(17);
    foreach ($keys as $key) {
        $cache->put($key, 'old value', 60);
    }

    app(ForgetBranchCacheAction::class)->handle(17);

    expect($store->forgotten)->toBe(count($keys) + 1);
    foreach ($keys as $key) {
        expect($cache->has($key))->toBeFalse();
    }
});

test('branch database cache invalidation rolls back with its enclosing source transaction', function (): void {
    $cache = prompt93BranchCache();
    $keys = ForgetBranchCacheAction::cacheKeysForBranch(17);
    foreach ($keys as $key) {
        $cache->put($key, 'original', 60);
    }

    expect(fn () => DB::transaction(function () use ($cache, $keys): void {
        app(ForgetBranchCacheAction::class)->handle(17);
        expect($cache->has($keys[0]))->toBeFalse();
        throw new RuntimeException('Rollback branch source change.');
    }))->toThrow(RuntimeException::class, 'Rollback branch source change.');

    foreach ($keys as $key) {
        expect($cache->get($key))->toBe('original');
    }
});

test('central branch cache action forgets guest menu and polling interval keys', function () {
    $branch = createPrompt93CachedBranch();
    $cache = prompt93BranchCache();

    warmPrompt93BranchCaches($branch);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeTrue()
        ->and($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'lt')))->toBeTrue()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeTrue();

    app(ForgetBranchCacheAction::class)->handle($branch->id);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeFalse()
        ->and($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'lt')))->toBeFalse()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();
});

test('polling interval cache can be forgotten directly', function () {
    $branch = createPrompt93CachedBranch();
    $cache = prompt93BranchCache();

    app(GetBranchPollingIntervalAction::class)->handle($branch->id);

    expect($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeTrue();

    GetBranchPollingIntervalAction::forgetForBranch($branch->id);
    GetBranchPollingIntervalAction::forgetForBranch(0);

    expect($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();
});

test('menu changes clear the centralized branch cache', function () {
    $branch = createPrompt93CachedBranch();
    $category = MenuCategory::query()
        ->select(['id', 'menu_id', 'name'])
        ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
        ->firstOrFail();
    $cache = prompt93BranchCache();

    warmPrompt93BranchCaches($branch);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeTrue()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeTrue();

    $category->update(['name' => 'Prompt 93 Updated Category']);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeFalse()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();
});

test('polling settings clear only polling cache and keep the guest menu generation', function () {
    $branch = createPrompt93CachedBranch();
    $settings = $branch->settings()->firstOrFail();
    $cache = prompt93BranchCache();

    warmPrompt93BranchCaches($branch);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeTrue()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeTrue();

    app(SaveBranchSettingsGroupAction::class)->handle($branch->organization->owner, $branch, 'advanced', [
        'polling_interval_seconds' => 3, 'inactivity_warning_minutes' => 45, 'pending_session_expire_minutes' => 30,
    ], BranchSettingsGroup::fingerprint($branch, $settings, 'advanced'), (string) Str::uuid());

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeTrue()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();
});

test('branch profile changes preserve catalog and polling while ancestor invalidation stays compatible', function () {
    $branch = createPrompt93CachedBranch();
    $cache = prompt93BranchCache();

    warmPrompt93BranchCaches($branch);
    $branch->update(['logo_path' => 'media/prompt-093/branch-logo.png', 'phone' => '+370 600 11111']);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeTrue()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeTrue();

    warmPrompt93BranchCaches($branch);
    $branch->brand()->firstOrFail()->update(['logo_path' => 'media/prompt-093/brand-logo.png']);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeFalse()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();

    warmPrompt93BranchCaches($branch);
    $branch->organization()->firstOrFail()->update(['logo_path' => 'media/prompt-093/organization-logo.png']);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeFalse()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();
});

test('settings invalidate only the cache that consumes their changed values', function (array $changes, bool $menuRemains, bool $pollingRemains): void {
    $branch = createPrompt93CachedBranch();
    $settings = $branch->settings()->firstOrFail();
    $cache = prompt93BranchCache();
    warmPrompt93BranchCaches($branch);
    $generation = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id]));

    $settings->update($changes);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id)))->toBe($menuRemains)
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBe($pollingRemains)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id])) === $generation)->toBe($menuRemains);
})->with([
    'language' => [['default_language' => 'lt'], false, true],
    'polling' => [['polling_interval_seconds' => 15], true, false],
    'guest access' => [['allow_guest_invite_links' => false], true, true],
    'settlement' => [['service_charge_enabled' => true, 'service_charge_basis_points' => 1500], true, true],
    'inactivity' => [['inactivity_warning_minutes' => 120], true, true],
]);

test('branch availability changes expire menus without resetting the polling cache', function (): void {
    $branch = createPrompt93CachedBranch();
    warmPrompt93BranchCaches($branch);

    $branch->update(['is_temporarily_closed' => true]);

    expect(prompt93BranchCache()->has(GetGuestMenuForBranchAction::cacheKey($branch->id)))->toBeFalse()
        ->and(prompt93BranchCache()->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeTrue();
});

test('an older catalog builder cannot replace an entry published for a newer generation', function (): void {
    $branch = createPrompt93CachedBranch();
    $cache = prompt93BranchCache();
    $key = GetGuestMenuForBranchAction::cacheKey($branch->id);
    $replacement = null;
    MenuItem::retrieved(function (MenuItem $item) use ($branch, $cache, $key, &$replacement): void {
        if ($replacement !== null) {
            return;
        }
        $replacement = [];
        $item->update(['is_available' => false]);
        $replacement = [
            'generation' => BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$branch->id])),
            'valid_until' => now()->addMinute()->toIso8601String(),
            'payload' => ['published' => 'new generation'],
        ];
        $cache->put($key, $replacement, 60);
    });

    app(GetGuestMenuForBranchAction::class)->handle($branch->id);

    expect($replacement)->not->toBeNull()->and($cache->get($key))->toBe($replacement);
});

test('a language change during default-language read does not publish under a newer generation', function (): void {
    $branch = createPrompt93CachedBranch();
    $settings = $branch->settings()->firstOrFail();
    $changed = false;
    DB::listen(function ($query) use ($settings, &$changed): void {
        if (! $changed && str_contains($query->sql, 'select "default_language" from "branch_settings"')) {
            $changed = true;
            $settings->update(['default_language' => 'lt']);
        }
    });

    $action = app(GetGuestMenuForBranchAction::class);
    $action->handle($branch->id, 'en');
    $payload = $action->handle($branch->id, 'en');

    expect($changed)->toBeTrue()->and($payload['default_language'])->toBe('lt');
});

function createPrompt93CachedBranch(): Branch
{
    $organization = Organization::factory()->withOwnerMembership()->create(['name' => 'Prompt 93 Group']);
    OrganizationSubscription::factory()->for($organization)->active()->create();
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 93 Brand']);
    $branch = app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Prompt 93 Branch',
        'address' => 'Cache Street 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => true,
    ], actor: $organization->owner);
    $menu = Menu::factory()->for($branch)->create([
        'name' => 'Prompt 93 Menu',
        'status' => MenuStatus::Active,
    ]);
    $category = MenuCategory::factory()->for($menu)->create([
        'name' => 'Prompt 93 Category',
        'is_active' => true,
    ]);

    MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Prompt 93 Dish',
            'price_cents' => 950,
            'is_available' => true,
        ]);

    return $branch;
}

function warmPrompt93BranchCaches(Branch $branch): void
{
    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');
    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'lt');
    app(GetBranchPollingIntervalAction::class)->handle($branch->id);
}

function prompt93BranchCache(): Repository
{
    return Cache::store(ForgetBranchCacheAction::cacheStore());
}
