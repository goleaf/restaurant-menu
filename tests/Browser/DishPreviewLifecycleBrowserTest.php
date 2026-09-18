<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\User;
use App\Support\MoneyFormatter;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create(['email' => 'dish.preview.lifecycle@example.test']);
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Preview lifecycle']);
    $this->branch = Branch::factory()->for($this->organization)->withDefaultSettings()->create(['timezone' => 'UTC']);
    $this->menu = Menu::factory()->for($this->branch)->create(['status' => MenuStatus::Active]);
    $this->category = MenuCategory::factory()->for($this->menu)->active()->create();
    $this->dish = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->withDistinctTranslations()
        ->create(['name' => 'Saved soup', 'is_available' => true, 'price_cents' => 1250]);
    $this->dishUrl = route('organizations.brands.branches.menu.dish.edit', [$this->organization, $this->branch->brand, $this->branch, $this->dish], false);
});

test('draft preview remains visibly stale after deferred input crosses section history', function (): void {
    $page = dishPreviewLifecycleOwner($this->owner, $this->dishUrl);
    $name = '#edit-menu-item-'.$this->dish->id.'-panel-en input[type=text]';
    $page->click('.rm-dish > header button[wire\:click="openPreview"]')->assertVisible('[data-menu-preview]')
        ->select('select[wire\:model="previewForm.source"]', 'draft')
        ->click('form[wire\:submit="refreshPreview"] button[type=submit]')->assertVisible('[data-dish-preview-price]');
    $savedPreviewName = $page->script('document.querySelector("[data-dish-preview-result] h3").textContent');
    $page->fill($name, 'Local soup revision')
        ->click('[data-menu-section="photos"]')->assertQueryStringHas('section', 'photos')
        ->click('[data-menu-section="main"]')->assertVisible('[data-dish-section="main"]')
        ->assertValue($name, 'Local soup revision')
        ->assertSee(__('dish.preview.refresh_needed'))->assertMissing('[data-dish-preview-price]');
    expect($page->script('document.querySelector("[data-dish-preview-result] h3").textContent'))->toBe($savedPreviewName);
    $page->click('form[wire\:submit="refreshPreview"] button[type=submit]')
        ->assertVisible('[data-dish-preview-price]')->assertSeeIn('[data-dish-preview-result]', 'Local soup revision')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($this->dish->fresh()->name)->toBe('Saved soup')->and(DraftOrder::query()->count())->toBe(0);
});

test('saving a variant invalidates an open saved preview while the main form keeps its local input', function (): void {
    $variant = MenuItemVariant::factory()->for($this->dish, 'item')->withTranslations()
        ->create(['name' => 'Large soup', 'price_cents' => 1400, 'is_default' => true, 'is_available' => true]);
    $page = dishPreviewLifecycleOwner($this->owner, $this->dishUrl);
    $name = '#edit-menu-item-'.$this->dish->id.'-panel-en input[type=text]';
    $page->fill($name, 'Unsaved name kept')
        ->click('.rm-dish > header button[wire\:click="openPreview"]')->assertVisible('[data-menu-preview]')
        ->select('select[wire\:model="previewForm.variantId"]', (string) $variant->id)
        ->click('form[wire\:submit="refreshPreview"] button[type=submit]')
        ->assertSeeIn('[data-dish-preview-price]', MoneyFormatter::formatCents(1400, $this->branch->currency))
        ->click('[data-menu-section="variants"]')->assertQueryStringHas('section', 'variants')
        ->click('button[wire\:click="startEditingVariant('.$variant->id.')"]')
        ->fill('input[wire\:model="editingVariant.variantPrice"]', '18.50')
        ->click('form[wire\:submit="updateVariant"] button[type=submit]')
        ->assertMissing('form[wire\:submit="updateVariant"]')
        ->assertSee(__('dish.preview.refresh_needed'))->assertMissing('[data-dish-preview-price]')
        ->click('[data-menu-section="main"]')->assertValue($name, 'Unsaved name kept')
        ->click('form[wire\:submit="refreshPreview"] button[type=submit]')
        ->assertSeeIn('[data-dish-preview-price]', MoneyFormatter::formatCents(1850, $this->branch->currency))
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($variant->fresh()->price_cents)->toBe(1850)->and($this->dish->fresh()->name)->toBe('Saved soup')
        ->and(DraftOrder::query()->count())->toBe(0);
});

function dishPreviewLifecycleOwner(User $owner, string $url): PendingAwaitablePage
{
    $page = visit(route('login', absolute: false));
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false));
    $page->navigate($url)->assertPresent('[data-page="dish-card"]')
        ->assertScript("getComputedStyle(document.querySelector('.rm-dish')).display", 'grid');

    return $page;
}
