<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\Branches\UpdateBranchSettingsAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

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

test('branch settings changes clear guest menu and polling caches', function () {
    $branch = createPrompt93CachedBranch();
    $settings = $branch->settings()->firstOrFail();
    $cache = prompt93BranchCache();

    warmPrompt93BranchCaches($branch);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeTrue()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeTrue();

    app(UpdateBranchSettingsAction::class)->handle($settings, [
        'require_waiter_confirmation_for_orders' => true,
        'allow_guest_created_sessions' => true,
        'allow_waiter_opened_sessions' => true,
        'allow_guest_invite_links' => true,
        'guest_join_requires_approval' => true,
        'polling_interval_seconds' => 3,
        'default_language' => 'lt',
        'default_currency' => 'EUR',
        'service_charge_enabled' => false,
        'tips_enabled' => false,
        'order_flow_mode' => BranchOrderFlowMode::WaiterConfirmation->value,
    ]);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeFalse()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();
});

test('logo changes clear cache for affected branches', function () {
    $branch = createPrompt93CachedBranch();
    $cache = prompt93BranchCache();

    warmPrompt93BranchCaches($branch);
    $branch->update(['logo_path' => 'media/prompt-093/branch-logo.png']);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeFalse()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();

    warmPrompt93BranchCaches($branch);
    $branch->brand()->firstOrFail()->update(['logo_path' => 'media/prompt-093/brand-logo.png']);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeFalse()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();

    warmPrompt93BranchCaches($branch);
    $branch->organization()->firstOrFail()->update(['logo_path' => 'media/prompt-093/organization-logo.png']);

    expect($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeFalse()
        ->and($cache->has(GetBranchPollingIntervalAction::cacheKey($branch->id)))->toBeFalse();
});

function createPrompt93CachedBranch(): Branch
{
    $organization = Organization::factory()->create(['name' => 'Prompt 93 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 93 Brand']);
    $branch = app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Prompt 93 Branch',
        'address' => 'Cache Street 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
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
