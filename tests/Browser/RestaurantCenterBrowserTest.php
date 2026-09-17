<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create(['email' => 'restaurant.center@example.test']);
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Organizacija — Организация']);
    $this->brand = Brand::factory()->for($this->organization)->create(['name' => 'Brand']);
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->withDefaultSettings()->create(['name' => 'First restaurant', 'timezone' => 'UTC']);
    $this->setup = RestaurantOnboarding::factory()->for($this->actor)->for($this->organization)->for($this->brand)->for($this->branch)->create();
});

test('restaurant properties save from one center and unsaved exit preserves the selected card', function (): void {
    $page = visit('/login')->fill('email', $this->actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false));
    $page->navigate(route('restaurants.index', ['object' => $this->branch->id, 'kind' => 'branch'], false))->assertPresent('[data-page="restaurant-center"]')
        ->fill('input[wire\:model="form.name"]', 'Renamed restaurant')
        ->click('form[wire\:submit="save"] button[type=submit]')->assertSee('Renamed restaurant');
    expect($this->branch->fresh()->name)->toBe('Renamed restaurant');
    $page->fill('input[wire\:model="form.name"]', 'Unsent name')->click('[data-page="restaurant-center"] a[href*="/restaurants/create"]')
        ->assertVisible('dialog[data-modal="menu-workspace-unsaved"]')
        ->click('button[x-on\:click="cancelNavigation"]')->assertValue('input[wire\:model="form.name"]', 'Unsent name');
    foreach ([320, 390, 768, 1024, 1440] as $width) {
        $page->resize($width, 900)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
            ->screenshot(false, 'restaurant-center-'.$width);
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('setup retains an unsaved room while saving a draft menu before any tables', function (): void {
    $page = visit('/login')->fill('email', $this->actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false));
    $page->navigate(route('restaurants.setup', ['setup' => $this->setup->id, 'step' => 2], false))
        ->assertPresent('[data-page="restaurant-setup"]')->fill('input[wire\:model="form.areaName"]', 'Unsaved room')
        ->click('nav button[wire\:click="goToStep(3)"]')->fill('input[wire\:model="form.menuName"]', 'Lunch')
        ->fill('input[wire\:model="form.categoryName"]', 'Soups')->fill('input[wire\:model="form.itemName"]', 'Soup')
        ->fill('input[wire\:model="form.itemPrice"]', '4.50')
        ->click('form[wire\:submit="createStarterMenu"] button[type=submit]')->assertSee('Lunch')
        ->click('button[wire\:click="goToStep(2)"]')->assertVisible('section[wire\:show="step === 2"]')->assertValue('input[wire\:model="form.areaName"]', 'Unsaved room')
        ->screenshot(false, 'restaurant-setup-retained-room')->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($this->setup->fresh()->menu_id)->not->toBeNull()->and($this->setup->fresh()->completed_at)->toBeNull();
});
