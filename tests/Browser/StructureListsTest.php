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
    $branches = Branch::factory()->count(16)->for($organization)->for($brand)->active()
        ->sequence(fn ($sequence): array => ['name' => sprintf('Service branch %02d', $sequence->index + 1)])
        ->create();
    $archivedTarget = $branches->firstOrFail();
    $editedTarget = $branches->last();
    $foreign = Branch::factory()->active()->create(['name' => 'Other tenant branch']);
    $brandsUrl = route('organizations.brands.index', $organization, false);
    $branchesUrl = route('organizations.brands.branches.index', [$organization, $brand], false);

    $page = visit(route('login', absolute: false));
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->navigate(route('organizations.index', absolute: false))->resize(1440, 1000)
        ->assertSee('South Family Restaurants')
        ->fill('#organizations-search', 'North')
        ->assertSee('North Family Restaurants')
        ->assertDontSee('South Family Restaurants')
        ->select('#organizations-sort', 'name_desc')
        ->assertScript('Livewire.find(document.querySelector("[data-page=organizations]").getAttribute("wire:id")).__instance.canonical.sort', 'name_desc')
        ->assertQueryStringHas('sort', 'name_desc')
        ->click('[data-page="organizations"] a[href$="'.$brandsUrl.'"]')
        ->assertPathIs($brandsUrl)
        ->assertSeeIn('header.rm-page-header', 'North Family Restaurants')
        ->assertSee('Evening Cafe')
        ->fill('#brands-search', 'Daily')
        ->assertSee('Daily Kitchen')
        ->assertDontSee('Evening Cafe')
        ->select('#brands-sort', 'name_desc')
        ->assertScript('Livewire.find(document.querySelector("[data-page=organization-brands]").getAttribute("wire:id")).__instance.canonical.sort', 'name_desc')
        ->assertQueryStringHas('sort', 'name_desc')
        ->click('[data-page="organization-brands"] a[href$="'.$branchesUrl.'"]')
        ->assertPathIs($branchesUrl)
        ->assertSeeIn('header.rm-page-header', 'North Family Restaurants')
        ->assertSeeIn('header.rm-page-header', 'Daily Kitchen')
        ->assertDontSee('Other tenant branch');

    $page->fill('#branches-search', 'Service branch')->select('#branches-sort', 'name_desc')
        ->assertSee('Service branch 16')->assertDontSee('Service branch 01')
        ->click('button[wire\\:click="nextPage(\'branchesPage\')"]')
        ->assertSee('Service branch 01')->assertDontSee('Service branch 16')
        ->click('button[wire\\:click="confirmDelete('.$archivedTarget->id.')"]')
        ->assertSee(__('structure.confirmations.archive.title'))
        ->click('button[wire\\:click="delete"]')
        ->assertSee('Service branch 16')->assertDontSee('Service branch 01')
        ->assertValue('#branches-search', 'Service branch')->assertValue('#branches-sort', 'name_desc')
        ->assertValue('#branches-lifecycle', 'active')
        ->assertScript('new URL(location.href).searchParams.get("branchesPage") || "1"', '1')
        ->assertSeeIn('[data-structure-visible-count]', __('structure.list.visible_count', ['count' => 15]));
    expect($archivedTarget->fresh()->trashed())->toBeTrue()
        ->and(Branch::query()->where('brand_id', $brand->id)->count())->toBe(15)
        ->and(Branch::withTrashed()->where('brand_id', $brand->id)->count())->toBe(16);

    $page->select('#branches-lifecycle', 'archived')->assertSee('Service branch 01')
        ->click('button[wire\\:click="restore('.$archivedTarget->id.')"]')
        ->assertVisible('[data-structure-empty="search"]')
        ->assertValue('#branches-search', 'Service branch')->assertValue('#branches-sort', 'name_desc')
        ->select('#branches-lifecycle', 'active')->assertSee('Service branch 16')
        ->fill('#branches-search', 'No matching branch')
        ->assertVisible('[data-structure-empty="search"]')
        ->click('[data-structure-empty] button')->assertValue('#branches-search', '')
        ->assertSee('Service branch 16')->assertValue('#branches-sort', 'name_desc');
    expect($archivedTarget->fresh()->trashed())->toBeFalse()
        ->and(Branch::query()->where('brand_id', $brand->id)->count())->toBe(16)
        ->and(Branch::onlyTrashed()->where('brand_id', $brand->id)->count())->toBe(0)
        ->and($foreign->fresh()->is_active)->toBeTrue()
        ->and($foreign->fresh()->trashed())->toBeFalse();

    foreach (['light', 'dark'] as $appearance) {
        $page->script("window.Flux.appearance = '{$appearance}'");
        foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
            $page->resize($width, $height)->assertScript('document.documentElement.scrollWidth <= innerWidth');
        }
    }
    $page->resize(390, 844)->screenshot(false, 'structure-branches-390-dark');
    $page->resize(1440, 1000)->script("window.Flux.appearance = 'light'");
    $page->screenshot(false, 'structure-branches-1440-light');

    $page->click('button[wire\\:click="startEditing('.$editedTarget->id.')"]')
        ->fill('input[name="editingName"]', 'Service branch unsaved draft')
        ->click('ui-switch[wire\\:model="editingIsActive"]')
        ->click('button[wire\\:click="update"]')
        ->assertValue('input[name="editingName"]', 'Service branch unsaved draft')
        ->click('[wire\\:key="branch-'.$editedTarget->id.'"] [data-flux-modal-trigger] button');
    $dialog = 'dialog[data-modal="suspend-branch-'.$editedTarget->id.'"]';
    $page->assertVisible($dialog)->fill($dialog.' textarea[name="branchSuspendReason"]', 'x')
        ->click($dialog.' button[wire\\:click="update"]')
        ->assertSeeIn($dialog, __('ui.livewire.organizations.brands.branches.index.the_suspension_reason_must'))
        ->assertValue($dialog.' textarea[name="branchSuspendReason"]', 'x')
        ->assertAttribute($dialog.' textarea[name="branchSuspendReason"]', 'aria-invalid', 'true')
        ->assertValue('input[name="editingName"]', 'Service branch unsaved draft')
        ->click($dialog.' [data-flux-modal-close] button')
        ->assertMissing($dialog.'[open]')
        ->assertValue('input[name="editingName"]', 'Service branch unsaved draft')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($editedTarget->fresh()->name)->toBe('Service branch 16')
        ->and($editedTarget->fresh()->is_active)->toBeTrue();
});
