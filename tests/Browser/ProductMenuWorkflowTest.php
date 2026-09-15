<?php

declare(strict_types=1);

use App\Enums\MenuStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Pest\Browser\Api\PendingAwaitablePage;
use Symfony\Component\HttpFoundation\Response;

test('owner edits all dish locales with keyboard tabs across responsive themes', function (): void {
    $this->withVite();
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['restaurant-menu.test', '127.0.0.1', 'localhost']);
    $this->seed(DemoRestaurantSeeder::class);
    $owner = DemoAccountCatalog::forRole(SystemRole::Owner);
    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $branch = Branch::query()->where('organization_id', $organization->id)->firstOrFail();
    $item = MenuItem::query()->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->firstOrFail();
    $page = visit(route('demo-login.index', absolute: false));
    productMenuClick($page, sprintf('form[action$="/demo-login/%s"] button[type="submit"]', $owner['role']->value));
    $page->assertPathIs(route('dashboard', absolute: false));
    $page->navigate(route('organizations.brands.branches.menu.index', [$organization, $branch->brand, $branch], false));
    productMenuClick($page, sprintf('button[wire\\:click="startEditingItem(%d)"]', $item->id));
    $prefix = '#edit-menu-item-'.$item->id;
    $page->assertAttribute($prefix.'-tab-en', 'aria-selected', 'true')
        ->keys($prefix.'-tab-en', 'ArrowRight')->assertAttribute($prefix.'-tab-lt', 'aria-selected', 'true')
        ->keys($prefix.'-tab-lt', 'ArrowRight')->assertAttribute($prefix.'-tab-ru', 'aria-selected', 'true');
    productMenuClick($page, $prefix.'-tab-lt');
    $page->fill($prefix.'-panel-lt input[type="text"]', '');
    productMenuClick($page, 'form[wire\\:submit="updateItem"] button[type="submit"]');
    $page->assertAttribute($prefix.'-tab-lt', 'aria-selected', 'true')
        ->assertPresent($prefix.'-panel-lt [role="alert"]');
    expect($page->script("document.querySelector('{$prefix}-panel-lt').closest('form').noValidate"))->toBeTrue();
    productMenuClick($page, $prefix.'-tab-ru');
    $page->assertEnabled('form[wire\\:submit="updateItem"] button[type="submit"]');
    productMenuClick($page, 'form[wire\\:submit="updateItem"] button[type="submit"]');
    $page->wait(1);
    $page->assertAttribute($prefix.'-tab-lt', 'aria-selected', 'true');
    productMenuClick($page, $prefix.'-tab-lt');
    productMenuClick($page, $prefix.'-panel-lt [data-copy-original=lt]');
    $page->assertPresent($prefix.'-panel-lt [data-copy-notice=lt]');
    expect($page->script("document.querySelector('{$prefix}-panel-lt input[type=text]').value"))->toBe($item->translations()->where('language_code', 'en')->value('name'));
    productMenuClick($page, $prefix.'-tab-ru');
    productMenuFillLocale($page, $prefix, 'ru', 'Браузерное блюдо', "Первая строка\nВторая строка");
    productMenuClick($page, $prefix.'-tab-lt');
    productMenuFillLocale($page, $prefix, 'lt', 'Naršyklės patiekalas', "Pirma eilutė\nAntra eilutė");
    productMenuClick($page, $prefix.'-tab-en');
    productMenuFillLocale($page, $prefix, 'en', 'Browser dish', "First line\nSecond line");
    $page->wait(1);
    productMenuClick($page, 'form[wire\\:submit="updateItem"] button[type="submit"]');
    $page->wait(1);
    $item->refresh();
    expect($item->name)->toBe('Browser dish')->and($item->description)->toBe("First line\nSecond line")
        ->and($item->translations()->where('language_code', 'lt')->value('name'))->toBe('Naršyklės patiekalas')
        ->and($item->translations()->where('language_code', 'ru')->value('name'))->toBe('Браузерное блюдо');

    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
        $catalogSpacing = $page->script(<<<'JAVASCRIPT'
            (() => {
                const actions = document.querySelector('[data-catalog-create-actions]');
                const firstMenu = document.querySelector('[data-section="menu-catalog"] [data-catalog-menu-list]');
                return actions && firstMenu ? firstMenu.getBoundingClientRect().top - actions.getBoundingClientRect().bottom : null;
            })()
        JAVASCRIPT);
        expect($catalogSpacing)->not->toBeNull()->toBeLessThanOrEqual(32);
        $page->script('window.scrollTo(0, 0)');
        $page->screenshot(false, "product-owner-{$width}x{$height}-light");
    }
    $page->script("document.documentElement.style.fontSize = '200%'");
    productMenuAssertNoOverflow($page, 1440, 1000);
    $page->script("document.documentElement.style.fontSize = ''; window.localStorage.setItem('flux.appearance', 'dark')");
    $page->navigate(route('organizations.brands.branches.menu.index', [$organization, $branch->brand, $branch], false));
    productMenuAssertNoOverflow($page, 390, 844);
    $page->screenshot(false, 'product-owner-390x844-dark');

    $page->navigate(route('organizations.brands.branches.settings.index', [$organization, $branch->brand, $branch], false))
        ->assertPresent('[data-page="branch-settings"]');
    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
        $page->screenshot(false, "product-settings-{$width}x{$height}-dark");
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    productMenuAssertNoFailedResources($page);
});

test('workspace protects copied translations and restores sections with browser history', function (): void {
    $this->withVite();
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['restaurant-menu.test', '127.0.0.1', 'localhost']);
    $this->seed(DemoRestaurantSeeder::class);
    $owner = DemoAccountCatalog::forRole(SystemRole::Owner);
    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $branch = Branch::query()->where('organization_id', $organization->id)->firstOrFail();
    $item = MenuItem::query()->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->firstOrFail();
    $item->translations()->where('language_code', 'lt')->firstOrFail()->update(['description' => null]);
    $item->translations()->where('language_code', 'en')->firstOrFail()->update(['description' => 'Original to translate']);
    $page = visit(route('demo-login.index', absolute: false));
    productMenuClick($page, sprintf('form[action$="/demo-login/%s"] button[type="submit"]', $owner['role']->value));
    $page->assertPathIs(route('dashboard', absolute: false));
    $page->navigate(route('organizations.brands.branches.menu.index', [$organization, $branch->brand, $branch], false));
    productMenuClick($page, sprintf('button[wire\\:click="startEditingItem(%d)"]', $item->id));
    $prefix = '#edit-menu-item-'.$item->id;
    productMenuClick($page, $prefix.'-tab-lt');
    productMenuClick($page, $prefix.'-panel-lt [data-copy-original=lt]');
    $page->assertVisible($prefix.'-panel-lt [data-copy-notice=lt]');
    productMenuClick($page, '[data-menu-section="modifiers"]');
    $page->assertSee(__('menu.workspace.unsaved_title'));
    productMenuClick($page, 'button[x-on\\:click="cancelNavigation"]');
    expect($page->script("document.querySelector('{$prefix}-panel-lt textarea').value"))->toBe('Original to translate');
    productMenuClick($page, '[data-menu-section="modifiers"]');
    productMenuClick($page, 'button[x-on\\:click="discardAndNavigate"]');
    $page->assertAttribute('[data-menu-section="modifiers"]', 'aria-current', 'page');
    productMenuClick($page, '[data-menu-section="departments"]');
    $page->assertAttribute('[data-menu-section="departments"]', 'aria-current', 'page');
    $page->script('window.history.back()');
    $page->wait(1);
    $page->assertAttribute('[data-menu-section="modifiers"]', 'aria-current', 'page');
    $page->script('window.history.forward()');
    $page->wait(1);
    $page->assertAttribute('[data-menu-section="departments"]', 'aria-current', 'page');
    expect($item->translations()->where('language_code', 'lt')->value('description'))->toBeNull();
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('owner manages accumulated pending photos without losing image identity', function (): void {
    $this->withVite();
    productMenuEnableMultipartFixtures();
    FileUploadConfiguration::storage();
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['restaurant-menu.test', '127.0.0.1', 'localhost']);
    $this->seed(DemoRestaurantSeeder::class);
    $owner = DemoAccountCatalog::forRole(SystemRole::Owner);
    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $branch = Branch::query()->where('organization_id', $organization->id)->firstOrFail();
    $item = MenuItem::query()->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->firstOrFail();
    $initialImageCount = $item->galleryImages()->count() + ($item->image === null ? 0 : 1);
    $page = visit(route('demo-login.index', absolute: false));
    productMenuClick($page, sprintf('form[action$="/demo-login/%s"] button[type="submit"]', $owner['role']->value));
    $page->assertPathIs(route('dashboard', absolute: false));
    $page->navigate(route('organizations.brands.branches.menu.index', [$organization, $branch->brand, $branch], false));
    productMenuClick($page, sprintf('button[wire\\:click="startEditingItem(%d)"]', $item->id));

    $picker = 'section[aria-labelledby="item-'.$item->id.'-photos-heading"]';
    $page->assertPresent('#item-images-'.$item->id);
    productMenuAttachPng($page, '#item-images-'.$item->id, 'first.png');
    productMenuAssertPendingFileCount($page, $picker, $item->id, 1);
    productMenuAttachPng($page, '#item-images-'.$item->id, 'second.png');
    productMenuAssertPendingFileCount($page, $picker, $item->id, 2);
    $pendingFiles = productMenuPendingFiles($page, $picker, $item->id);
    expect($pendingFiles)->toHaveCount(2);
    expect($page->script("[...document.querySelectorAll('{$picker} figure figcaption span[x-text]')].map(span => span.textContent)"))->toBe(['first.png', 'second.png']);
    expect($page->script("(async () => { window.dispatchEvent(new Event('offline')); await new Promise(resolve => setTimeout(resolve, 0)); return [...document.querySelectorAll('{$picker} input[type=file], {$picker} figure:has(span[x-text]) button, {$picker} button[wire\\\\:click^=saveItemImages]')].every(button => button.disabled); })()"))->toBeTrue();
    expect(productMenuPendingFiles($page, $picker, $item->id))->toBe($pendingFiles);
    $page->script("(async () => { window.dispatchEvent(new Event('online')); await new Promise(resolve => setTimeout(resolve, 0)); const buttons=document.querySelectorAll('{$picker} figure button'); buttons[0].click(); buttons[1].click(); })()");
    productMenuAssertPendingFileCount($page, $picker, $item->id, 1);
    expect(productMenuPendingFiles($page, $picker, $item->id))->toBe([$pendingFiles[1]]);
    expect($page->script("[...document.querySelectorAll('{$picker} figure figcaption span[x-text]')].map(span => span.textContent)"))->toBe(['second.png']);
    productMenuAttachPng($page, '#item-images-'.$item->id, 'third.png');
    productMenuAssertPendingFileCount($page, $picker, $item->id, 2);
    productMenuClick($page, sprintf('button[wire\\:click="saveItemImages(%d)"]', $item->id));
    $page->assertCount($picker.' figure[wire\\:key^="menu-item-'.$item->id.'-image-"]', $initialImageCount + 2);
    expect($item->fresh()->galleryImages()->count() + ($item->image === null ? 0 : 1))->toBe($initialImageCount + 2);
    $galleryOrder = $item->galleryImages()->orderBy('sort_order')->pluck('id')->all();
    productMenuClick($page, 'button[wire\\:click^="reorderItemImages"]:not([disabled])');
    $page->wait(1);
    expect($item->galleryImages()->orderBy('sort_order')->pluck('id')->all())->not->toBe($galleryOrder);
    $primaryPath = $item->fresh()->image;
    productMenuClick($page, 'button[wire\\:click^="promoteItemImage"]');
    $page->wait(1);
    expect($item->fresh()->image)->not->toBe($primaryPath);
    $unchangedPath = $item->fresh()->image;
    productMenuClick($page, 'button[wire\\:click^="editItemImagePresentation"]');
    $page->assertPresent('[data-image-presentation-editor]');
    $page->keys('#image-focal-x-'.$item->id, 'Home')->keys('#image-focal-x-'.$item->id, 'ArrowRight');
    $page->keys('#image-focal-y-'.$item->id, 'End');
    foreach (['en' => 'Fresh dish photo', 'lt' => 'Šviežio patiekalo nuotrauka', 'ru' => 'Фото свежего блюда'] as $locale => $alt) {
        productMenuClick($page, '#image-'.$item->id.'-'.$locale.'-tab');
        $page->fill('[data-photo-locale="'.$locale.'"] input', $alt)
            ->fill('[data-photo-locale="'.$locale.'"] textarea', $alt.' caption');
    }
    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
        $page->script("document.querySelector('[data-image-presentation-editor]').scrollIntoView({ block: 'start' })");
        $page->screenshot(false, "product-photo-editor-{$width}");
    }
    productMenuClick($page, 'button[wire\\:click="saveItemImagePresentation"]');
    $page->wait(1)->assertNotPresent('[data-image-presentation-editor]');
    expect($item->fresh()->image)->toBe($unchangedPath)
        ->and($item->fresh()->image_presentation['focal_x'])->toBe(1)
        ->and($item->fresh()->image_presentation['focal_y'])->toBe(100)
        ->and($item->fresh()->image_presentation['translations']['ru']['alt'])->toBe('Фото свежего блюда');

    productMenuAttachPng($page, '#item-images-'.$item->id, 'first.png');
    $page->wait(1);
    productMenuClick($page, 'button[wire\\:click="cancelItemEditing"]');
    $page->wait(1);
    productMenuClick($page, '[data-menu-section="availability"]');
    $page->assertAttribute('[data-menu-section="availability"]', 'aria-current', 'page');
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('owner previews local CSV discards safely and applies a page scoped availability change', function (): void {
    $this->withVite();
    productMenuEnableMultipartFixtures();
    FileUploadConfiguration::storage();
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['restaurant-menu.test', '127.0.0.1', 'localhost']);
    $this->seed(DemoRestaurantSeeder::class);
    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $branch = Branch::query()->where('organization_id', $organization->id)->firstOrFail();
    $menu = Menu::query()->where('branch_id', $branch->id)->firstOrFail();
    $category = MenuCategory::query()->where('menu_id', $menu->id)->where('is_active', true)->firstOrFail();
    $page = visit(route('demo-login.index', absolute: false));
    productMenuClick($page, 'form[action$="/demo-login/owner"] button[type="submit"]');
    $page->assertPathIs(route('dashboard', absolute: false));
    $page->navigate(route('organizations.brands.branches.menu.index', [$organization, $branch->brand, $branch, 'section' => 'transfer'], false));
    $page->assertPresent('[data-section="catalog-transfer"]');
    $page->select('select[wire\\:model\\.live="form.menuId"]', (string) $menu->id);
    productMenuAttachCsv($page, 'invalid header');
    $page->wait(1);
    productMenuClick($page, 'button[wire\\:click="discardImport"]');
    $page->wait(1);
    productMenuClick($page, '[data-menu-section="catalog"]');
    $page->assertAttribute('[data-menu-section="catalog"]', 'aria-current', 'page');
    productMenuClick($page, '[data-menu-section="transfer"]');
    $page->assertPresent('[data-section="catalog-transfer"]');
    productMenuAttachCsv($page, 'invalid header');
    $page->wait(1);
    productMenuClick($page, 'form[wire\\:submit="previewImport"] button[type="submit"]');
    $page->assertPresent('[data-section="catalog-transfer"] [role="alert"]');
    productMenuClick($page, 'button[wire\\:click="discardImport"]');
    $page->wait(1);
    productMenuClick($page, '[data-menu-section="catalog"]');
    $page->assertAttribute('[data-menu-section="catalog"]', 'aria-current', 'page');
    productMenuClick($page, '[data-menu-section="transfer"]');
    $page->assertPresent('[data-section="catalog-transfer"]');
    $csv = 'id,category_id,price,name_en,description_en,name_lt,description_lt,name_ru,description_ru'."\n".
        ','.$category->id.',12.34,Browser CSV dish,Vegetables,Naršyklės patiekalas,Daržovės,Блюдо CSV,Овощи';
    productMenuAttachCsv($page, $csv);
    $page->wait(1);
    productMenuClick($page, 'form[wire\\:submit="previewImport"] button[type="submit"]');
    $page->assertSee('Browser CSV dish');
    expect(MenuItem::query()->where('name', 'Browser CSV dish')->exists())->toBeFalse();
    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
        $page->screenshot(false, "product-csv-{$width}");
    }
    productMenuClick($page, 'button[wire\\:click="applyImport"]');
    $page->assertSee(__('menu.csv.imported', ['count' => 1]));
    $imported = MenuItem::query()->where('name', 'Browser CSV dish')->sole();
    expect($imported->price_cents)->toBe(1234)->and($imported->is_available)->toBeFalse();
    productMenuClick($page, 'button[wire\\:click="exportCatalog"]');
    $page->assertPresent('[data-section="catalog-transfer"] [role="status"]');
    productMenuClick($page, '[data-menu-section="catalog"]');
    $page->assertAttribute('[data-menu-section="catalog"]', 'aria-current', 'page');
    $page->fill('input[name="filters.search"]', 'Browser CSV dish')->wait(1);
    productMenuClick($page, 'button[wire\\:click="selectCatalogPage"]');
    $page->assertSee(__('menu.bulk.selected', ['count' => 1]));
    $page->select('select[wire\\:model\\.live="bulk.operation"]', 'available')->wait(1);
    productMenuClick($page, 'form[wire\\:submit="applyCatalogBulk"] button[type="submit"]');
    $page->assertSee(__('menu.bulk.saved', ['count' => 1]));
    expect($imported->fresh()->is_available)->toBeTrue();
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('browse only guest opens bounded gallery and escape restores focus', function (): void {
    $this->withVite();
    $fixtureDirectoryName = 'browser-fixtures/'.bin2hex(random_bytes(8));
    $fixtureDirectory = public_path($fixtureDirectoryName);
    File::ensureDirectoryExists($fixtureDirectory);
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($fixtureDirectory));
    config()->set('filesystems.disks.public.root', $fixtureDirectory);
    config()->set('filesystems.disks.public.url', '/'.$fixtureDirectoryName);
    Storage::forgetDisk('public');
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create([
        'currency' => 'EUR',
        'is_temporarily_closed' => true,
        'temporary_closed_reason' => 'Browse only verification',
    ]);
    BranchSetting::factory()->for($branch)->create(['default_language' => 'lt']);
    $point = ServicePoint::factory()->for($branch)->create(['status' => ServicePointStatus::Occupied, 'is_active' => true]);
    $qr = QrCode::factory()->for($point)->create(['status' => QrCodeStatus::Active]);
    $session = TableSession::factory()->forServicePoint($point)->active()->waiterOpened()->create();
    $guest = TableSessionGuest::factory()->for($session)->create(['status' => TableSessionGuestStatus::Active, 'locale' => 'lt']);
    $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->active()->create(['name' => 'Ilga lietuviška kategorija']);
    $primaryImagePath = 'primary.png';
    $galleryImagePath = 'gallery.png';
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAD0lEQVR42mP8z8AARMAgAAQAAf8CBZ0AAAAASUVORK5CYII=', true);
    expect($png)->not->toBeFalse();
    Storage::disk('public')->put($primaryImagePath, $png);
    Storage::disk('public')->put($galleryImagePath, $png);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create([
        'name' => 'Labai ilgas lietuviškas patiekalo pavadinimas ekranų patikrai',
        'description' => "Pirma aprašymo eilutė.\nAntra aprašymo eilutė.",
        'image' => $primaryImagePath, 'is_available' => false,
    ]);
    MenuItemImage::factory()->for($item, 'item')->create(['path' => $galleryImagePath]);
    $draftOrder = DraftOrder::factory()->for($session)->create();
    $draftItem = DraftOrderItem::factory()->for($draftOrder)->for($guest, 'guest')->for($item, 'menuItem')->create([
        'item_name' => 'Basket sentinel',
        'quantity' => 1,
    ]);
    $this->withCookie('guest_token_'.substr(hash('sha256', $qr->public_token), 0, 24), $guest->guest_token);
    $page = visit(route('public.qr.show', ['token' => $qr->public_token], false));
    $page->resize(320, 800)->assertSee($item->name)->assertSee(__('menu.guest.view_details', [], 'lt'));
    productMenuClick($page, '#guest-menu-item-details-'.$item->id);
    $page->assertPresent('[role="dialog"][aria-modal="true"]')->assertSee('Antra aprašymo eilutė.')
        ->assertPresent('button[aria-label="'.__('menu.guest.gallery_next', [], 'lt').'"]')
        ->assertNotPresent('button[wire\\:click="saveConfiguredItem"]');
    $visibleGalleryImagesLoaded = $page->script(<<<'JAVASCRIPT'
        Promise.race([
            Promise.all([...document.querySelectorAll('[role="dialog"] img')]
                .filter((image) => getComputedStyle(image).display !== 'none')
                .map((image) => image.complete
                    ? image.naturalWidth > 0
                    : new Promise((resolve) => {
                        image.addEventListener('load', () => resolve(image.naturalWidth > 0), { once: true });
                        image.addEventListener('error', () => resolve(false), { once: true });
                    })))
                .then((loaded) => loaded.length > 0 && loaded.every(Boolean)),
            new Promise((resolve) => setTimeout(() => resolve(false), 5000)),
        ])
    JAVASCRIPT);
    expect($visibleGalleryImagesLoaded)->toBeTrue();
    $page->screenshot(false, 'product-guest-dialog-320x800');
    $page->keys('button[aria-label="'.__('menu.guest.gallery_next', [], 'lt').'"]', 'Tab');
    expect($page->script("document.activeElement?.closest('[role=dialog]') !== null"))->toBeTrue();
    $page->keys('[data-guest-dish-dialog][open] button[aria-label="'.__('menu.guest.close', [], 'lt').'"]', 'Shift+Tab');
    expect($page->script("document.activeElement?.closest('[role=dialog]') !== null"))->toBeTrue();
    $page->keys('[role="dialog"]', 'Escape')->assertMissing('[role="dialog"][aria-modal="true"]');
    $page->assertPresent('#guest-menu-item-details-'.$item->id.':focus');
    $page->fill('input[wire\\:model\.live\.debounce\.250ms="search"]', 'Labai')->wait(1);
    productMenuClick($page, 'button[wire\\:click="$set(\'selectedCategoryId\', '.$category->id.')"]');
    $guestMenuComponentId = $page->script("document.querySelector('#guest-menu-language-{$branch->id}')?.closest('[data-component=guest-menu]')?.getAttribute('wire:id')");
    $offlineLanguages = $page->script(<<<'JAVASCRIPT'
        (() => {
            const selectors = [...document.querySelectorAll('#guest-page-language, [id^="guest-menu-language-"]')];
            const before = selectors.map(select => select.value);
            window.dispatchEvent(new Event('offline'));
            const disabled = selectors.map(select => select.disabled);
            window.dispatchEvent(new Event('online'));
            return { before, disabled, after: selectors.map(select => select.value), enabledAgain: selectors.every(select => !select.disabled) };
        })()
    JAVASCRIPT);
    expect($offlineLanguages['disabled'])->toBe([true, true])
        ->and($offlineLanguages['before'])->toBe($offlineLanguages['after'])
        ->and($offlineLanguages['enabledAgain'])->toBeTrue();
    $page->select('#guest-menu-language-'.$branch->id, 'ru')->wait(1)
        ->assertSee('Basket sentinel')
        ->assertSee(__('guest.cart.title', [], 'ru'));
    $selectedCategoryPressed = $page->script(<<<JAVASCRIPT
        [...document.querySelectorAll('button')]
            .find((button) => button.getAttribute('wire:click') === "\$set('selectedCategoryId', {$category->id})")
            ?.getAttribute('aria-pressed')
    JAVASCRIPT);
    expect($page->script("document.querySelector('#guest-page-language')?.value"))->toBe('ru');
    expect($page->script("document.querySelector('#guest-menu-language-{$branch->id}')?.closest('[data-component=guest-menu]')?.getAttribute('wire:id')"))->toBe($guestMenuComponentId)
        ->and($page->script("[...document.querySelectorAll('input')].find((input) => input.getAttribute('wire:model.live.debounce.250ms') === 'search')?.value"))->toBe('Labai')
        ->and($selectedCategoryPressed)->toBe('true');
    expect($draftItem->fresh()->id)->toBe($draftItem->id)
        ->and($draftItem->draft_order_id)->toBe($draftOrder->id)
        ->and($guest->fresh()->table_session_id)->toBe($session->id)
        ->and($guest->refresh()->locale)->toBe('ru');
    $page->select('#guest-page-language', 'lt')->wait(1)
        ->assertSee(__('guest.cart.title', [], 'lt'));
    expect($page->script("document.querySelector('#guest-menu-language-{$branch->id}')?.value"))->toBe('lt')
        ->and($draftItem->fresh()->id)->toBe($draftItem->id)
        ->and($draftItem->draft_order_id)->toBe($draftOrder->id)
        ->and($guest->fresh()->table_session_id)->toBe($session->id)
        ->and($guest->refresh()->locale)->toBe('lt');
    foreach (['en', 'lt', 'ru'] as $locale) {
        $page->select('#guest-page-language', $locale)->wait(1)
            ->assertSee(__('guest.cart.title', [], $locale));

        foreach (['light', 'dark'] as $appearance) {
            $page->script("window.Flux.appearance = '{$appearance}'");
            $page->assertScript("document.documentElement.classList.contains('dark')", $appearance === 'dark');

            foreach ([[320, 800], [360, 800], [390, 844], [430, 900], [768, 900], [1024, 900], [1440, 1000], [1920, 1080]] as [$width, $height]) {
                productMenuAssertNoOverflow($page, $width, $height);
            }
        }
    }
    $page->script("window.Flux.appearance = 'system'");
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    productMenuAssertNoFailedResources($page);
});

test('guest dismisses dish details without waiting for a network response', function (): void {
    $this->withVite();
    $branch = Branch::factory()->create();
    BranchSetting::factory()->for($branch)->create(['default_language' => 'en']);
    $point = ServicePoint::factory()->for($branch)->create(['is_active' => true]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $guest = TableSessionGuest::factory()->for($session)->active()->create(['locale' => 'en']);
    $this->withCookie('guest_token_'.substr(hash('sha256', $qr->public_token), 0, 24), $guest->guest_token);
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->active()->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Offline details']);
    $page = visit(route('public.qr.show', ['token' => $qr->public_token], false));
    productMenuClick($page, '#guest-menu-item-details-'.$item->id);
    $page->assertPresent('[role="dialog"]');
    $dismissed = $page->script(<<<'JAVASCRIPT'
        (async () => {
            const originalFetch = window.fetch;
            const pending = [];
            let requests = 0;
            window.fetch = (...args) => {
                if (!String(args[0]).includes('/livewire')) return originalFetch(...args);
                requests++;
                return new Promise((resolve, reject) => pending.push(() => originalFetch(...args).then(resolve, reject)));
            };
            window.dispatchEvent(new Event('offline'));
            try {
                const dialog = document.querySelector('[role="dialog"]');
                dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
                await new Promise(resolve => setTimeout(resolve, 150));
                return { visible: dialog.isConnected && getComputedStyle(dialog).display !== 'none', focus: document.activeElement?.id, requests };
            } finally {
                window.fetch = originalFetch;
                window.dispatchEvent(new Event('online'));
                pending.forEach(resume => resume());
            }
        })()
    JAVASCRIPT);
    expect($dismissed['visible'])->toBeFalse()
        ->and($dismissed['focus'])->toBe('guest-menu-item-details-'.$item->id)
        ->and($dismissed['requests'])->toBe(0);
    $page->wait(1);
    expect($page->script("document.querySelector('[role=dialog]')?.checkVisibility() ?? false"))->toBeFalse();
    productMenuClick($page, '#guest-menu-item-details-'.$item->id);
    $page->assertVisible('[role="dialog"]');
    expect($page->script('getComputedStyle(document.documentElement).overflow'))->toBe('hidden');
    productMenuClick($page, 'button[aria-label="'.__('menu.guest.close').'"]');
    $page->assertMissing('[role="dialog"]');
    $page->assertPresent('#guest-menu-item-details-'.$item->id.':focus');
    expect($page->script('getComputedStyle(document.documentElement).overflow'))->not->toBe('hidden');
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('guest recovers a configured dish after an availability conflict without retyping', function (): void {
    $this->withVite();
    $branch = Branch::factory()->create();
    BranchSetting::factory()->for($branch)->create(['default_language' => 'en']);
    $point = ServicePoint::factory()->for($branch)->create(['is_active' => true]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $guest = TableSessionGuest::factory()->for($session)->active()->create(['locale' => 'en']);
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->active()->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Recovery dish', 'is_available' => true]);
    $group = ModifierGroup::factory()->for($branch)->create(['is_required' => false, 'min_select' => 0, 'max_select' => 1]);
    $option = ModifierOption::factory()->for($group)->create(['is_available' => true]);
    $item->modifierGroups()->attach($group->id);
    $this->withCookie('guest_token_'.substr(hash('sha256', $qr->public_token), 0, 24), $guest->guest_token);
    $page = visit(route('public.qr.show', ['token' => $qr->public_token], false));
    $page->resize(390, 844);
    productMenuClick($page, '#guest-menu-item-details-'.$item->id);
    $page->assertPresent('[role="dialog"]');
    productMenuClick($page, 'button[wire\\:click^="toggleModifierOption"]');
    $page->fill('textarea[wire\\:model="itemComment"]', 'Please keep this comment');
    productMenuAssertOfflineAction($page, 'saveConfiguredItem');
    $item->update(['is_available' => false]);
    productMenuClick($page, 'button[wire\\:click="saveConfiguredItem"]');
    $page->assertSee(__('menu.guest.item_no_longer_available'))
        ->assertNotPresent('button[wire\\:click="saveConfiguredItem"]');
    productMenuAssertOfflineAction($page, 'refreshConfiguredItem');
    expect($page->script('[...document.querySelectorAll("textarea")].find(field => field.getAttribute("wire:model") === "itemComment")?.value'))->toBe('Please keep this comment')
        ->and(DraftOrderItem::query()->count())->toBe(0);
    productMenuAssertNoOverflow($page, 320, 800);
    $page->screenshot(false, 'product-guest-availability-conflict-320');

    $item->update(['is_available' => true]);
    productMenuClick($page, 'button[wire\\:click="refreshConfiguredItem"]');
    $page->assertDontSee(__('menu.guest.item_no_longer_available'))
        ->assertPresent('button[wire\\:click="saveConfiguredItem"]');
    productMenuClick($page, 'button[wire\\:click="saveConfiguredItem"]');
    $page->assertNotPresent('[role="dialog"]');
    $line = DraftOrderItem::query()->sole();
    expect($line->comment)->toBe('Please keep this comment')
        ->and($line->selected_modifiers[0]['option_id'])->toBe($option->id)
        ->and($line->table_session_guest_id)->toBe($guest->id);
    productMenuClick($page, '#guest-menu-item-details-'.$item->id);
    $page->assertPresent('[role="dialog"]');
    $item->update(['hidden_until' => now()->addHour()]);
    productMenuClick($page, 'button[wire\\:click="saveConfiguredItem"]');
    $page->assertSee(__('menu.guest.item_no_longer_available'));
    $page->keys('[role="dialog"]', 'Escape')->assertMissing('[role="dialog"]');
    $page->assertPresent('#guest-menu-title-'.$branch->id.':focus');
    expect(DraftOrderItem::query()->count())->toBe(1);
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    productMenuAssertNoFailedResources($page);
});

test('populated restaurant work screens remain responsive and keyboard reachable', function (SystemRole $role, string $routeName, string $pageSelector): void {
    $this->withVite();
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['restaurant-menu.test', '127.0.0.1', 'localhost']);
    $this->seed(DemoRestaurantSeeder::class);
    $identity = DemoAccountCatalog::forRole($role);
    $page = visit(route('demo-login.index', absolute: false));
    productMenuClick($page, sprintf('form[action$="/demo-login/%s"] button[type="submit"]', $identity['role']->value));
    $page->assertPathIs(route('dashboard', absolute: false));
    $page->navigate(route($routeName, absolute: false))->assertPresent($pageSelector);

    $offlineState = $page->script(<<<'JAVASCRIPT'
        (() => {
            const mutations = [...document.querySelectorAll('button')].filter(button => /^(setItemStatus|openTable|markWaiterCallHandled|disableTemporaryClosure)\(/.test(button.getAttribute('wire:click') ?? ''));
            const enabled = mutations.filter(button => !button.disabled);
            window.dispatchEvent(new Event('offline'));
            return { count: enabled.length, disabled: enabled.every(button => button.disabled) };
        })()
    JAVASCRIPT);
    $onlineState = $page->script(<<<'JAVASCRIPT'
        (() => {
            window.dispatchEvent(new Event('online'));
            return [...document.querySelectorAll('button')].some(button => /^(setItemStatus|openTable|markWaiterCallHandled|disableTemporaryClosure)\(/.test(button.getAttribute('wire:click') ?? '') && !button.disabled);
        })()
    JAVASCRIPT);
    expect($offlineState['count'])->toBeGreaterThan(0)
        ->and($offlineState['disabled'])->toBeTrue()
        ->and($onlineState)->toBeTrue();

    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
        $page->screenshot(false, "product-{$role->value}-{$width}x{$height}-light");
    }

    $page->script("window.localStorage.setItem('flux.appearance', 'dark')");
    $page->navigate(route($routeName, absolute: false));
    productMenuAssertNoOverflow($page, 390, 844);
    $page->screenshot(false, "product-{$role->value}-390x844-dark");

    if ($role === SystemRole::Waiter) {
        $tableUrl = $page->script('document.querySelector(\'a[href*="/restaurant/waiter/tables/"]\')?.getAttribute("href")');
        expect($tableUrl)->toBeString();
        $page->navigate($tableUrl);
        foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
            productMenuAssertNoOverflow($page, $width, $height);
            $page->screenshot(false, "product-waiter-detail-{$width}x{$height}-dark");
        }
        $page->script("window.localStorage.setItem('flux.appearance', 'light')");
        $page->navigate($tableUrl);
        productMenuAssertNoOverflow($page, 390, 844);
        $page->screenshot(false, 'product-waiter-detail-390x844-light');
    }

    $page->keys('body[class]', 'Tab');
    expect($page->script('document.activeElement !== document.body'))->toBeTrue();
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    productMenuAssertNoFailedResources($page);
})->with([
    'waiter' => [SystemRole::Waiter, 'restaurant.waiter.dashboard', '[data-page="waiter-dashboard"]'],
    'kitchen' => [SystemRole::HeadChef, 'restaurant.kitchen.dashboard', '[data-page="kitchen-dashboard"]'],
    'bar' => [SystemRole::Bartender, 'restaurant.bar.dashboard', '[data-page="bar-dashboard"]'],
]);

function productMenuFillLocale(PendingAwaitablePage $page, string $prefix, string $locale, string $name, string $description): void
{
    $panel = $prefix.'-panel-'.$locale;
    $page->fill($panel.' input[type="text"]', $name)->fill($panel.' textarea', $description);
}

function productMenuAssertOfflineAction(PendingAwaitablePage $page, string $action): void
{
    $encoded = json_encode($action, JSON_THROW_ON_ERROR);
    $state = $page->script(<<<JAVASCRIPT
        (() => {
            const button = [...document.querySelectorAll('button')].find(button => button.getAttribute('wire:click') === {$encoded});
            if (!button) return null;
            const initiallyEnabled = !button.disabled;
            window.dispatchEvent(new Event('offline'));
            const disabledOffline = button.disabled;
            window.dispatchEvent(new Event('online'));
            return { initiallyEnabled, disabledOffline, enabledAgain: !button.disabled };
        })()
    JAVASCRIPT);
    expect($state)->toBe(['initiallyEnabled' => true, 'disabledOffline' => true, 'enabledAgain' => true]);
}

function productMenuClick(PendingAwaitablePage $page, string $selector): void
{
    $page->assertVisible('css='.$selector.' >> nth=0')->assertEnabled('css='.$selector.' >> nth=0');
    $encoded = json_encode($selector, JSON_THROW_ON_ERROR);
    expect($page->script("(() => { const element = document.querySelector({$encoded}); element.scrollIntoView({block: 'center'}); if (!element.checkVisibility() || element.disabled) return false; element.click(); return true; })()"))->toBeTrue();
}

function productMenuAttachPng(PendingAwaitablePage $page, string $selector, string $name): void
{
    $page->assertEnabled($selector);
    $encodedSelector = json_encode($selector, JSON_THROW_ON_ERROR);
    $encodedName = json_encode($name, JSON_THROW_ON_ERROR);
    $encodedBytes = json_encode(base64_encode(UploadedFile::fake()->image($name, 800, 400)->getContent()), JSON_THROW_ON_ERROR);
    $attached = $page->script(<<<JAVASCRIPT
        (() => {
            const input = document.querySelector({$encodedSelector});
            if (!(input instanceof HTMLInputElement)) return false;
            const bytes = Uint8Array.from(atob({$encodedBytes}), character => character.charCodeAt(0));
            const transfer = new DataTransfer();
            transfer.items.add(new File([bytes], {$encodedName}, { type: 'image/png' }));
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        })()
    JAVASCRIPT);
    expect($attached)->toBeTrue();
}

function productMenuAssertNoOverflow(PendingAwaitablePage $page, int $width, int $height): void
{
    $page->resize($width, $height);
    $state = $page->script(<<<'JAVASCRIPT'
        (() => {
            const root = document.documentElement;
            const offenders = [...document.querySelectorAll('body *')].filter((element) => {
                const rect = element.getBoundingClientRect();
                return getComputedStyle(element).display !== 'none' && rect.width > 0 && (rect.left < -1 || rect.right > root.clientWidth + 1);
            }).slice(0, 8).map((element) => ({ tag: element.tagName, class: String(element.className).slice(0, 140), text: element.textContent?.trim().slice(0, 80), rect: element.getBoundingClientRect().toJSON() }));
            return { client: root.clientWidth, scroll: root.scrollWidth, offenders };
        })()
    JAVASCRIPT);
    expect($state['scroll'])->toBeLessThanOrEqual($state['client'], "Horizontal overflow at {$width}x{$height}: ".json_encode($state['offenders'], JSON_THROW_ON_ERROR));
}

function productMenuAssertNoFailedResources(PendingAwaitablePage $page): void
{
    $failed = $page->script(<<<'JAVASCRIPT'
        performance.getEntriesByType('resource')
            .filter((entry) => typeof entry.responseStatus === 'number' && entry.responseStatus >= 400)
            .map((entry) => ({ name: entry.name, status: entry.responseStatus }))
    JAVASCRIPT);
    expect($failed)->toBeEmpty();
}

/** Decode only this test's allowlisted bounded PNG and CSV fixtures; Pest 4.3.1 omits multipart files. */
function productMenuEnableMultipartFixtures(): void
{
    $middleware = new class
    {
        public function handle(Request $request, Closure $next): Response
        {
            $type = $request->header('Content-Type', '');
            if (! str_starts_with($type, 'multipart/form-data') || ! str_contains($request->path(), 'livewire')) {
                return $next($request);
            }

            expect(preg_match('/boundary="?([^";]+)"?/', $type, $matches))->toBe(1);
            $body = $request->getContent();
            expect(strlen($body))->toBeLessThan(100_000);
            $files = [];
            foreach (explode('--'.$matches[1], $body) as $part) {
                if (! str_contains($part, 'name="files[]"')) {
                    continue;
                }
                [$headers, $content] = explode("\r\n\r\n", $part, 2);
                expect(preg_match('/filename="([^"]+)"/', $headers, $filename))->toBe(1);
                expect($filename[1])->toBeIn(['first.png', 'second.png', 'third.png', 'catalog-fixture.csv']);
                $files[] = UploadedFile::fake()->createWithContent($filename[1], substr($content, 0, -2));
            }
            expect($files)->toHaveCount(1);
            $request->files->set('files', $files);

            return $next($request);
        }
    };
    app()->instance('browser.multipart-fixtures', $middleware);
    app(Kernel::class)->prependMiddleware('browser.multipart-fixtures');
}

function productMenuAssertPendingFileCount(PendingAwaitablePage $page, string $picker, int $itemId, int $count): void
{
    $encodedPicker = json_encode($picker, JSON_THROW_ON_ERROR);
    $page->assertScript(<<<JAVASCRIPT
        (() => {
            const component = document.querySelector({$encodedPicker}).closest('[data-section=menu-catalog]');
            const value = Livewire.find(component.getAttribute('wire:id')).\$get('itemImageUploads')[{$itemId}];
            const files = typeof value === 'string' && value.startsWith('livewire-files:')
                ? JSON.parse(value.slice('livewire-files:'.length)) : value;
            return Array.isArray(files) ? files.length : 0;
        })()
    JAVASCRIPT, $count);
}

/** @return list<string> */
function productMenuPendingFiles(PendingAwaitablePage $page, string $picker, int $itemId): array
{
    $value = $page->script("Livewire.find(document.querySelector('{$picker}').closest('[data-section=menu-catalog]').getAttribute('wire:id')).\$get('itemImageUploads')[{$itemId}]");
    if (is_string($value)) {
        expect($value)->toStartWith('livewire-files:');

        return json_decode(substr($value, strlen('livewire-files:')), true, flags: JSON_THROW_ON_ERROR);
    }

    expect($value)->toBeArray();

    return $value;
}

function productMenuAttachCsv(PendingAwaitablePage $page, string $contents): void
{
    $bytes = json_encode(base64_encode($contents), JSON_THROW_ON_ERROR);
    expect($page->script(<<<JAVASCRIPT
        (() => {
            const input = document.querySelector('[data-section="catalog-transfer"] input[type="file"]');
            if (!input) return false;
            const transfer = new DataTransfer();
            transfer.items.add(new File([Uint8Array.from(atob({$bytes}), c => c.charCodeAt(0))], 'catalog-fixture.csv', {type:'text/csv'}));
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', {bubbles:true}));
            return true;
        })()
    JAVASCRIPT))->toBeTrue();
}
