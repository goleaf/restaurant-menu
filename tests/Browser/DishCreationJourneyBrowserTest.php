<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\User;
use App\Support\MoneyFormatter;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\Support\BrowserMenuImageUpload;
use Tests\Support\IsolatedBrowserIdentity;

test('an owner creates one unpublished dish and completes photos variants and an independent shared group copy in its card', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    BrowserMenuImageUpload::enableMultipartFixtures();
    FileUploadConfiguration::storage();
    $fixtureName = 'browser-fixtures/'.bin2hex(random_bytes(8));
    $fixturePath = public_path($fixtureName);
    File::ensureDirectoryExists($fixturePath);
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($fixturePath));
    config()->set('filesystems.disks.public.root', $fixturePath);
    config()->set('filesystems.disks.public.url', '/'.$fixtureName);
    Storage::forgetDisk('public');
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create(['email' => 'dish.creation@example.test']);
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'One card restaurant']);
    $branch = Branch::factory()->for($organization)->withDefaultSettings()->create(['timezone' => 'UTC']);
    $menu = Menu::factory()->for($branch)->create(['name' => 'Seasonal creation menu', 'status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->active()->create(['name' => 'Warm dishes']);
    $other = MenuItem::factory()->for($menu)->for($category, 'category')->withDistinctTranslations()->create(['name' => 'Other existing dish']);
    $shared = ModifierGroup::factory()->for($branch)->withTranslations()->optional()->create(['name' => 'Shared toppings']);
    $sharedOption = ModifierOption::factory()->for($shared, 'group')->withTranslations()
        ->create(['name' => 'Fresh herbs', 'price_delta_cents' => 150, 'is_available' => true]);
    $other->modifierGroups()->attach($shared);
    $otherBefore = $other->fresh()->getRawOriginal();
    $sharedValues = $shared->only(['name', 'is_required', 'min_select', 'max_select', 'sort_order']);
    $sharedTranslations = $shared->translations()->orderBy('language_code')->get()->toArray();
    $optionBefore = $sharedOption->fresh()->getRawOriginal();
    $createUrl = route('organizations.brands.branches.menu.dish.create', [$organization, $branch->brand, $branch, 'menu' => $menu->id], false);
    $page = visit(route('login', absolute: false));
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->navigate($createUrl)->assertPresent('#edit-menu-item-new-tab-en')
        ->assertSee(__('dish.create.continue'));
    expect(MenuItem::query()->count())->toBe(1);
    $page->script(<<<'JS'
        window.dishCreationMetrics = { requests: 0, successful: 0, active: 0, request_bytes: 0, response_bytes: 0, section_changes: 0, form_submissions: 0, unsaved_dialogs: 0 };
        const metrics = window.dishCreationMetrics;
        const unsubscribe = Livewire.interceptRequest(({ request, onSend, onSuccess, onFinish }) => {
            onSend(() => {
                metrics.requests++;
                metrics.active++;
                metrics.request_bytes += new TextEncoder().encode(request.options.body).length;
            });
            onSuccess(({ body }) => {
                metrics.successful++;
                metrics.response_bytes += new TextEncoder().encode(body).length;
            });
            onFinish(() => metrics.active--);
        });
        const recordClick = event => { if (event.target.closest('[data-page="dish-card"] [data-menu-section]')) metrics.section_changes++; };
        const recordSubmit = event => { if (event.target.closest('[data-page="dish-card"]')) metrics.form_submissions++; };
        const dialogs = new MutationObserver(records => {
            for (const record of records) {
                if (record.target.matches('dialog[data-modal="menu-workspace-unsaved"][open]')) metrics.unsaved_dialogs++;
            }
        });
        dialogs.observe(document.documentElement, { subtree: true, attributes: true, attributeFilter: ['open'] });
        document.addEventListener('click', recordClick, { capture: true });
        document.addEventListener('submit', recordSubmit, { capture: true });
        window.disposeDishCreationMetrics = () => {
            unsubscribe();
            dialogs.disconnect();
            document.removeEventListener('click', recordClick, { capture: true });
            document.removeEventListener('submit', recordSubmit, { capture: true });
        };
        void 0;
        JS);
    $page->assertSeeIn('ui-select[wire\:model\.live="editingItemForm.itemMenuId"] [data-flux-select-button]', $menu->name)
        ->click('ui-select[wire\:model="editingItemForm.itemCategoryId"] [data-flux-select-button]')
        ->click('ui-select[wire\:model="editingItemForm.itemCategoryId"] ui-option[value="'.$category->id.'"]');
    creationJourneyTranslations($page, 'edit-menu-item-new', ['en' => 'One card soup', 'lt' => 'Vienos kortelės sriuba', 'ru' => 'Суп в одной карточке'], 'Saved recipe');
    $page->fill('input[wire\:model="editingItemForm.itemPrice"]', '12.50')
        ->click('form[wire\:submit="saveItem"] button[type=submit]')
        ->assertMissing('#edit-menu-item-new-tab-en')->assertPresent('[data-page="dish-card"]');
    $dish = MenuItem::query()->where('menu_id', $menu->id)->where('name', 'One card soup')->sole();
    $dishUrl = route('organizations.brands.branches.menu.dish.edit', [$organization, $branch->brand, $branch, $dish], false);
    $prefix = 'edit-menu-item-'.$dish->id;
    $page->assertPathIs($dishUrl)->click('#'.$prefix.'-tab-en')
        ->fill('#'.$prefix.'-panel-en textarea', 'Unsaved description kept through all sections')
        ->click('[data-menu-section="photos"]')->assertVisible('[data-dish-section="photos"]');
    expect(MenuItem::query()->count())->toBe(2)->and($dish->is_available)->toBeFalse()
        ->and($dish->translations()->orderBy('language_code')->pluck('name', 'language_code')->all())
        ->toBe(['en' => 'One card soup', 'lt' => 'Vienos kortelės sriuba', 'ru' => 'Суп в одной карточке']);

    BrowserMenuImageUpload::attachPng($page, '#item-images-'.$dish->id, 'first.png');
    $upload = 'button[wire\:click="saveItemImages('.$dish->id.')"]';
    $page->assertEnabled($upload)->click($upload)
        ->assertCount('[data-dish-section="photos"] figure[wire\:key^="menu-item-'.$dish->id.'-image-"]', 1)
        ->assertScript('(() => { const images = Array.from(document.querySelectorAll("[data-dish-section=photos] figure img")); return images.length > 0 && images.every(image => image.complete && image.naturalWidth > 0); })()', true)
        ->click('[data-menu-section="main"]')->assertValue('#'.$prefix.'-panel-en textarea', 'Unsaved description kept through all sections');
    expect($dish->fresh()->image)->not->toBeNull()->and(Storage::disk('public')->exists($dish->fresh()->image))->toBeTrue()
        ->and($dish->fresh()->description)->toBe('Saved recipe en');

    $page->click('[data-menu-section="variants"]')->assertVisible('[data-dish-section="variants"]')
        ->assertNotPresent('select[name="variantItemId"]');
    foreach ([['Small bowl', 'Maža porcija', 'Малая порция', '10.00'], ['Large bowl', 'Didelė porcija', 'Большая порция', '16.00']] as [$english, $lithuanian, $russian, $price]) {
        creationJourneyTranslations($page, 'variant.variantTranslations', ['en' => $english, 'lt' => $lithuanian, 'ru' => $russian]);
        $page->fill('input[name="variant.variantPrice"]', $price)
            ->click('form[wire\:submit="createVariant"] button[type=submit]')
            ->assertSeeIn('[data-dish-section="variants"] article[wire\:key^="menu-item-variant-"] span.font-semibold', $english);
    }
    expect($dish->variants()->orderBy('price_cents')->pluck('price_cents')->all())->toBe([1000, 1600]);
    $page->click('[data-menu-section="modifiers"]')->assertVisible('[data-dish-section="modifiers"]')
        ->assertNotPresent('select[name="assignment.modifierItemId"]')
        ->assertValue('select[name="assignment.modifierItemGroupId"]', (string) $shared->id)
        ->click('form[wire\:submit="attachModifierGroupToItem"] button[type=submit]')
        ->assertSeeIn('div[wire\:key="modifier-group-'.$shared->id.'"] h2', $shared->name);
    expect($dish->modifierGroups()->sole()->id)->toBe($shared->id)->and($other->modifierGroups()->sole()->id)->toBe($shared->id);
    $page->click('button[wire\:click="startCloningGroup('.$shared->id.')"]')
        ->fill('input[name="cloneName"]', 'Soup only toppings')
        ->click('form[wire\:submit="cloneGroup"] button[type=submit]')
        ->assertMissing('form[wire\:submit="cloneGroup"]');
    $copy = $dish->modifierGroups()->sole();
    $page->assertSeeIn('div[wire\:key="modifier-group-'.$copy->id.'"] h2', 'Soup only toppings');
    $copyOption = $copy->options()->sole();
    expect($copy->id)->not->toBe($shared->id)->and($copyOption->id)->not->toBe($sharedOption->id)
        ->and($copyOption->price_delta_cents)->toBe(150)
        ->and($other->fresh()->getRawOriginal())->toBe($otherBefore)
        ->and($other->modifierGroups()->sole()->id)->toBe($shared->id)
        ->and($shared->fresh()->only(array_keys($sharedValues)))->toBe($sharedValues)
        ->and($shared->translations()->orderBy('language_code')->get()->toArray())->toBe($sharedTranslations)
        ->and($sharedOption->fresh()->getRawOriginal())->toBe($optionBefore);

    $large = $dish->variants()->where('name', 'Large bowl')->sole();
    $page->click('[data-menu-section="main"]')->click('#'.$prefix.'-tab-en')
        ->assertValue('#'.$prefix.'-panel-en textarea', 'Unsaved description kept through all sections')
        ->click('.rm-dish > header button[wire\:click="openPreview"]')->assertVisible('[data-menu-preview]')
        ->select('select[name="previewForm.variantId"]', (string) $large->id)
        ->click('[data-menu-preview] [data-flux-checkbox][value="'.$copyOption->id.'"]')
        ->click('form[wire\:submit="refreshPreview"] button[type=submit]')
        ->assertSeeIn('[data-dish-preview-price]', MoneyFormatter::formatCents(1750, $branch->currency))
        ->assertSeeIn('[data-dish-preview-result]', 'Saved recipe en')
        ->assertPathIs($dishUrl)->screenshot(false, 'dish-creation-complete-preview')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs()
        ->assertScript('window.dishCreationMetrics.active', 0);
    $metrics = $page->script('window.disposeDishCreationMetrics(); ({ ...window.dishCreationMetrics })');
    expect($metrics['requests'])->toBeGreaterThan(0)->and($metrics['successful'])->toBe($metrics['requests'])
        ->and($metrics['request_bytes'])->toBeGreaterThan(0)->and($metrics['response_bytes'])->toBeGreaterThan(0)
        ->and($metrics['section_changes'])->toBe(5)->and($metrics['form_submissions'])->toBe(6)
        ->and($metrics['unsaved_dialogs'])->toBe(0);
    fwrite(STDOUT, 'DISH_CREATION_NETWORK '.json_encode(['scope' => 'Actual Livewire JSON requests after opening create; excludes document/assets and multipart PNG upload',
        'measurements' => $metrics], JSON_THROW_ON_ERROR).PHP_EOL);
    expect($dish->fresh()->is_available)->toBeFalse()->and($dish->fresh()->description)->toBe('Saved recipe en')
        ->and(MenuItem::query()->count())->toBe(2)->and(DraftOrder::query()->count())->toBe(0)->and(Order::query()->count())->toBe(0);
});

/** @param array<string,string> $names */
function creationJourneyTranslations(PendingAwaitablePage $page, string $prefix, array $names, ?string $description = null): void
{
    foreach ($names as $locale => $name) {
        $page->click('[id="'.$prefix.'-tab-'.$locale.'"]')->assertAttribute('[id="'.$prefix.'-tab-'.$locale.'"]', 'aria-selected', 'true')
            ->fill('[id="'.$prefix.'-panel-'.$locale.'"] input[type=text]', $name);
        if ($description !== null) {
            $page->fill('[id="'.$prefix.'-panel-'.$locale.'"] textarea', $description.' '.$locale);
        }
    }
}
