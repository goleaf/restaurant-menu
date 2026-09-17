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
        ->assertScript('document.getElementById(document.querySelector("dialog[data-modal=menu-workspace-unsaved]").getAttribute("aria-labelledby"))?.textContent.trim()', __('menu.workspace.unsaved_title'))
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
        ->click('nav button[wire\:click="goToStep(2)"]')->assertVisible('section[wire\:show="step === 2"]')->assertValue('input[wire\:model="form.areaName"]', 'Unsaved room')
        ->screenshot(false, 'restaurant-setup-retained-room')->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($this->setup->fresh()->menu_id)->not->toBeNull()->and($this->setup->fresh()->completed_at)->toBeNull();
});

test('center properties and four setup groups wrap long translated content in both themes', function (string $locale): void {
    $this->actor->update(['locale' => $locale]);
    $this->organization->update(['name' => 'Šeimos restoranų organizacija — Семейные рестораны и праздничные залы']);
    $this->brand->update(['name' => 'Seasonal neighborhood kitchen — Sezoninė virtuvė — Сезонная кухня']);
    $this->branch->update(['name' => 'Riverside restaurant with a garden terrace — Ресторан у реки с террасой']);
    $page = visit(route('login', ['lang' => $locale], false));
    $page->fill('email', $this->actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false));

    foreach ([
        'list' => route('restaurants.index', absolute: false),
        'properties' => route('restaurants.index', ['object' => $this->branch->id, 'kind' => 'branch'], false),
        'setup' => route('restaurants.setup', ['setup' => $this->setup->id, 'step' => 2], false),
    ] as $surface => $url) {
        $page->navigate($url)->assertAttribute('html[lang]', 'lang', $locale);
        foreach (['light', 'dark'] as $theme) {
            $page->script('window.Flux.appearance = '.json_encode($theme, JSON_THROW_ON_ERROR));
            foreach ([320, 390, 768, 1024, 1440] as $width) {
                $page->resize($width, 900)
                    ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                    ->assertScript(<<<'JS'
                        Array.from(document.querySelectorAll('.rm-restaurant-center :is([data-flux-button], [data-flux-select-button])'))
                            .filter(element => element.checkVisibility())
                            .every(element => element.getBoundingClientRect().height >= 44 && element.scrollWidth <= element.clientWidth + 1)
                        JS, true);
                if (($width === 390 && $theme === 'dark') || ($width === 1440 && $theme === 'light')) {
                    $page->screenshot(false, "restaurant-center-{$surface}-{$locale}-{$theme}-{$width}");
                }
            }
            $page->resize(780, 900)->script("document.documentElement.style.zoom = '2'");
            $page->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->assertScript(<<<'JS'
                    Array.from(document.querySelectorAll('.rm-restaurant-center :is([data-flux-button], [data-flux-select-button])'))
                        .filter(element => element.checkVisibility())
                        .every(element => element.scrollWidth <= element.clientWidth + 1)
                    JS, true);
            $page->script("document.documentElement.style.zoom = '1'");
        }
        if ($surface === 'setup') {
            foreach ([3, 4, 1, 2] as $step) {
                $page->click('nav button[aria-controls="restaurant-setup-group-'.$step.'"]')
                    ->assertAttribute('nav button[aria-controls="restaurant-setup-group-'.$step.'"]', 'aria-current', 'step')
                    ->assertVisible('#restaurant-setup-group-'.$step)
                    ->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
            }
        }
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['en', 'lt', 'ru']);

test('invalid setup selects retain actual keyboard focus with forced colors and reduced motion', function (): void {
    $page = visit(route('login', absolute: false), ['forcedColors' => 'active', 'reducedMotion' => 'reduce']);
    $page->fill('email', $this->actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->navigate(route('restaurants.create', ['organization' => $this->organization->id, 'brand' => $this->brand->id], false))
        ->resize(390, 844)
        ->fill('input[wire\:model="form.branchName"]', 'New restaurant')
        ->fill('input[wire\:model="form.branchAddress"]', 'Example 42')
        ->fill('input[wire\:model="form.branchCity"]', 'Vilnius')
        ->click('ui-select[wire\:model="form.branchCurrency"] [data-flux-select-button]')
        ->click('ui-option[value="EUR"]')
        ->click('form[wire\:submit="createRestaurant"] button[type=submit]')
        ->assertScript('document.activeElement.closest("ui-select")?.getAttribute("wire:model")', 'form.branchCountryCode')
        ->assertScript('document.activeElement.matches("button[data-flux-select-button][data-invalid]")', true)
        ->assertScript('document.getElementById(document.activeElement.getAttribute("aria-describedby"))?.matches("[data-flux-error]")', true)
        ->assertScript('document.getElementById(document.activeElement.getAttribute("aria-describedby"))?.textContent.trim().length > 0', true)
        ->assertScript('matchMedia("(forced-colors: active)").matches', true)
        ->assertScript('matchMedia("(prefers-reduced-motion: reduce)").matches', true)
        ->assertScript('getComputedStyle(document.activeElement).outlineStyle !== "none" || getComputedStyle(document.activeElement).boxShadow !== "none"', true)
        ->assertScript('document.activeElement.getBoundingClientRect().height >= 44', true)
        ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
        ->screenshot(false, 'restaurant-setup-forced-colors-invalid-country')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('same-name restaurants retain visible parent context and keyboard dialog focus', function (): void {
    $otherOrganization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Other restaurant organization']);
    $otherBrand = Brand::factory()->for($otherOrganization)->create(['name' => $this->brand->name]);
    $otherBranch = Branch::factory()->for($otherOrganization)->for($otherBrand)->withDefaultSettings()->create(['name' => $this->branch->name]);
    $page = visit('/login')->fill('email', $this->actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->navigate(route('restaurants.index', absolute: false))->resize(390, 844);
    $page->assertSee($this->organization->name)->assertSee($otherOrganization->name)
        ->keys('article[wire\\:key="branch-'.$otherBranch->id.'"] a[href*="object='.$otherBranch->id.'"]', 'Enter')
        ->assertScript('document.querySelector("[data-center-parent=organization]")?.textContent', $otherOrganization->name)
        ->assertScript('document.querySelector("[data-center-parent=brand]")?.textContent', $otherBrand->name)
        ->keys('[data-center-lifecycle-trigger]', 'Enter')
        ->assertVisible('dialog[data-modal="structure-lifecycle"]')
        ->assertScript('document.getElementById(document.querySelector("dialog[data-modal=structure-lifecycle]").getAttribute("aria-labelledby"))?.textContent.includes('.json_encode($otherBranch->name, JSON_THROW_ON_ERROR).')', true)
        ->assertScript('document.activeElement.closest("dialog")?.dataset.modal', 'structure-lifecycle')
        ->keys('dialog[data-modal="structure-lifecycle"] [autofocus]', 'Escape')
        ->assertNotVisible('dialog[data-modal="structure-lifecycle"]')
        ->assertScript('document.activeElement.matches("[data-center-lifecycle-trigger]")', true)
        ->fill('input[wire\\:model="form.name"]', '')
        ->click('form[wire\\:submit="save"] button[type=submit]')
        ->assertAttribute('input[wire\\:model="form.name"]', 'aria-invalid', 'true')
        ->assertScript('document.getElementById(document.querySelector("input[aria-invalid=true]").getAttribute("aria-describedby"))?.textContent.trim().length > 0', true)
        ->assertScript('document.querySelector("[data-center-parent=organization]")?.textContent', $otherOrganization->name)
        ->screenshot(false, 'restaurant-center-same-name-context-error')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($otherBranch->fresh()->name)->toBe($this->branch->name)
        ->and($this->branch->fresh()->name)->toBe('First restaurant');
});

test('brand creation keeps its selected organization visible and errors linked through keyboard editing', function (): void {
    $page = visit('/login')->fill('email', $this->actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->navigate(route('restaurants.index', ['view' => 'structure', 'organization' => $this->organization->id, 'create' => 'brand'], false))
        ->resize(320, 844)
        ->assertSee($this->organization->name)
        ->click('[data-structure-create] button[type=submit]')
        ->assertAttribute('input[name="structure_name"]', 'aria-invalid', 'true')
        ->assertScript('document.getElementById(document.querySelector("input[name=structure_name]").getAttribute("aria-describedby"))?.textContent.trim().length > 0', true)
        ->fill('input[name="structure_name"]', 'Keyboard brand')
        ->keys('input[name="structure_name"]', 'Enter')
        ->assertMissing('[data-structure-create]')
        ->assertSee('Keyboard brand')
        ->assertScript('document.querySelector("[data-center-parent=organization]")?.textContent', $this->organization->name)
        ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
        ->screenshot(false, 'restaurant-center-brand-created-320')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    expect(Brand::query()->where('organization_id', $this->organization->id)->where('name', 'Keyboard brand')->count())->toBe(1);
});

test('mobile center exposes its first restaurant action while secondary filters remain keyboard accessible', function (): void {
    $page = visit('/login')->fill('email', $this->actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->navigate(route('restaurants.index', absolute: false))->resize(390, 844)
        ->assertScript(<<<'JS'
            (() => {
                const action = document.querySelector('.rm-restaurant-center__row .rm-restaurant-center__actions a');
                const bounds = action.getBoundingClientRect();
                return bounds.top >= 0 && bounds.bottom <= innerHeight;
            })()
            JS, true)
        ->assertAttribute('[data-center-filter-toggle]', 'aria-expanded', 'false')
        ->assertNotVisible('select[wire\\:model\\.live="filters.sort"]')
        ->keys('[data-center-filter-toggle]', 'Enter')
        ->assertAttribute('[data-center-filter-toggle]', 'aria-expanded', 'true')
        ->select('select[wire\\:model\\.live="filters.sort"]', 'name_desc')
        ->assertQueryStringHas('sort', 'name_desc')
        ->keys('[data-center-filter-toggle]', 'Enter')
        ->assertAttribute('[data-center-filter-toggle]', 'aria-expanded', 'false')
        ->assertValue('select[wire\\:model\\.live="filters.sort"]', 'name_desc')
        ->assertScript('document.querySelectorAll("select[wire\\:model\\.live=\\\"filters.sort\\\"]").length', 1)
        ->screenshot(true, 'restaurant-center-mobile-collapsed-filters')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
