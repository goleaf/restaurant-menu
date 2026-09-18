<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Tests\Support\IsolatedBrowserIdentity;

test('structure lists retain hierarchy filters and drafts while recovering archived pages through real requests', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create(['email' => 'structure-owner@example.test', 'password' => 'password']);
    $owner->roles()->attach(Role::query()->where('code', SystemRole::Owner->value)->firstOrFail());
    $organization = Organization::factory()->for($owner, 'owner')->create(['name' => 'North Family Restaurants']);
    OrganizationUser::factory()->forOrganization($organization)->forUser($owner)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $excludedOrganization = Organization::factory()->for($owner, 'owner')->create(['name' => 'South Family Restaurants']);
    OrganizationUser::factory()->forOrganization($excludedOrganization)->forUser($owner)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $brand = Brand::factory()->for($organization)->create(['name' => 'Daily Kitchen']);
    Brand::factory()->for($organization)->create(['name' => 'Evening Cafe']);
    $branches = Branch::factory()->count(21)->for($organization)->for($brand)->active()
        ->sequence(fn ($sequence): array => ['name' => sprintf('Service branch %02d', $sequence->index + 1)])
        ->create();
    $archivedTarget = $branches->firstOrFail();
    $editedTarget = $branches->last();
    $foreign = Branch::factory()->active()->create(['name' => 'Other tenant branch']);

    $page = visit(route('login', absolute: false));
    $search = 'input[wire\\:model\\.live\\.debounce\\.300ms="filters.search"]';
    $sort = 'select[wire\\:model\\.live="filters.sort"]';
    $lifecycle = 'select[wire\\:model\\.live="filters.lifecycle"]';
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->navigate(route('restaurants.index', ['view' => 'structure'], false))->resize(1440, 1000)
        ->assertSee('South Family Restaurants')->fill($search, 'North')
        ->assertSee('North Family Restaurants')->assertDontSee('South Family Restaurants')
        ->click('[data-center-filter-toggle]')->select($sort, 'name_desc')->assertQueryStringHas('sort', 'name_desc')
        ->click('article[wire\\:key="organization-'.$organization->id.'"] a[href*="view=structure"]:not([href*="object="])')
        ->assertQueryStringHas('organization', (string) $organization->id)
        ->assertSee('Evening Cafe')->fill($search, 'Daily')
        ->assertSee('Daily Kitchen')->assertDontSee('Evening Cafe')
        ->click('[data-center-filter-toggle]')->assertValue($sort, 'name_desc')->assertQueryStringHas('sort', 'name_desc')
        ->click('article[wire\\:key="brand-'.$brand->id.'"] a[href*="brand='.$brand->id.'"]')
        ->assertQueryStringHas('brand', (string) $brand->id)->assertDontSee('Other tenant branch');

    $page->fill($search, 'Service branch')->assertQueryStringHas('q', 'Service branch')
        ->click('[data-center-filter-toggle]')->assertValue($sort, 'name_desc')
        ->assertSee('Service branch 21')->assertDontSee('Service branch 01')
        ->click('button[wire\\:click="nextPage(\'page\')"]')
        ->assertSee('Service branch 01')->assertDontSee('Service branch 21')
        ->click('article[wire\\:key="branch-'.$archivedTarget->id.'"] a[href*="object='.$archivedTarget->id.'"]')
        ->assertQueryStringHas('object', (string) $archivedTarget->id)
        ->click('button[wire\\:click="$set(\'confirming\', true)"]')
        ->assertVisible('dialog[data-modal="structure-lifecycle"]')
        ->fill('input[wire\\:model="confirmation"]', $archivedTarget->name)
        ->click('form[wire\\:submit="changeLifecycle"] button[type="submit"]')
        ->assertSee('Service branch 21')->assertDontSee('Service branch 01')
        ->assertValue($search, 'Service branch')->assertValue($sort, 'name_desc')->assertValue($lifecycle, 'active')
        ->assertScript('new URL(location.href).searchParams.get("page") || "1"', '1');
    expect($archivedTarget->fresh()->trashed())->toBeTrue()
        ->and(Branch::query()->where('brand_id', $brand->id)->count())->toBe(20)
        ->and(Branch::withTrashed()->where('brand_id', $brand->id)->count())->toBe(21);

    $page->click('[data-center-filter-toggle]')->select($lifecycle, 'archived')->assertSee('Service branch 01')
        ->click('article[wire\\:key="branch-'.$archivedTarget->id.'"] a[href*="object='.$archivedTarget->id.'"]')
        ->click('button[wire\\:click="$set(\'confirming\', true)"]')
        ->assertVisible('dialog[data-modal="structure-lifecycle"]')
        ->fill('input[wire\\:model="confirmation"]', $archivedTarget->name)
        ->click('form[wire\\:submit="changeLifecycle"] button[type="submit"]')
        ->assertVisible('[data-center-empty="search"]')->assertValue($search, 'Service branch')->assertValue($sort, 'name_desc')
        ->click('[data-center-filter-toggle]')->select($lifecycle, 'active')->assertSee('Service branch 21')
        ->fill($search, 'No matching branch')->assertVisible('[data-center-empty="search"]')
        ->click('button[wire\\:click="clearFilters"]')->assertValue($search, '')
        ->assertSee('Service branch 21')->assertValue($sort, 'name_desc');
    expect($archivedTarget->fresh()->trashed())->toBeFalse()->and($archivedTarget->fresh()->is_active)->toBeFalse()
        ->and(Branch::query()->where('brand_id', $brand->id)->count())->toBe(21)
        ->and(Branch::onlyTrashed()->where('brand_id', $brand->id)->count())->toBe(0)
        ->and($foreign->fresh()->is_active)->toBeTrue()->and($foreign->fresh()->trashed())->toBeFalse();

    foreach (['light', 'dark'] as $appearance) {
        $page->script("window.Flux.appearance = '{$appearance}'");
        foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
            $page->resize($width, $height)->assertScript('document.documentElement.scrollWidth <= innerWidth');
        }
    }
    $page->resize(390, 844)->screenshot(false, 'structure-branches-390-dark');
    $page->resize(1440, 1000)->script("window.Flux.appearance = 'light'");
    $page->screenshot(false, 'structure-branches-1440-light');

    $page->click('article[wire\\:key="branch-'.$editedTarget->id.'"] a[href*="object='.$editedTarget->id.'"]')
        ->fill('input[wire\\:model="form.name"]', 'Service branch unsaved draft')
        ->click('ui-checkbox[wire\\:model="form.isActive"]')
        ->click('form[wire\\:submit="save"] button[type="submit"]')
        ->assertValue('input[wire\\:model="form.name"]', 'Service branch unsaved draft')
        ->assertVisible('textarea[wire\\:model="form.suspensionReason"]')
        ->fill('textarea[wire\\:model="form.suspensionReason"]', 'x')
        ->click('form[wire\\:submit="save"] button[type="submit"]')
        ->assertAttribute('textarea[wire\\:model="form.suspensionReason"]', 'aria-invalid', 'true')
        ->assertValue('textarea[wire\\:model="form.suspensionReason"]', 'x')
        ->assertValue('input[wire\\:model="form.name"]', 'Service branch unsaved draft')
        ->click('aside.rm-restaurant-center__panel > a[wire\\:navigate]')
        ->assertVisible('dialog[data-modal="menu-workspace-unsaved"]')
        ->click('button[x-on\\:click="cancelNavigation"]')
        ->assertValue('input[wire\\:model="form.name"]', 'Service branch unsaved draft')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($editedTarget->fresh()->name)->toBe('Service branch 21')->and($editedTarget->fresh()->is_active)->toBeTrue();
});
