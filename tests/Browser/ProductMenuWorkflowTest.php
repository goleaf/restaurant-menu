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
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\PendingAwaitablePage;

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

    productMenuClick($page, sprintf('button[wire\\:click="startEditingItem(%d)"]', $item->id));
    $page->wait(1);
    productMenuAttachPng($page, '#item-images-'.$item->id, 'first.png');
    $page->wait(1);
    productMenuClick($page, sprintf('button[wire\\:click="saveItemImages(%d)"]', $item->id));
    $page->wait(1);
    productMenuAttachPng($page, '#item-images-'.$item->id, 'second.png');
    $page->wait(1);
    productMenuClick($page, sprintf('button[wire\\:click="saveItemImages(%d)"]', $item->id));
    $page->wait(1)->assertPresent('button[wire\\:click^="promoteItemImage"]')->assertPresent('button[wire\\:click^="reorderItemImages"]');
    expect($item->fresh()->galleryImages()->count() + ($item->image === null ? 0 : 1))->toBeGreaterThanOrEqual(2);
    $galleryOrder = $item->galleryImages()->orderBy('sort_order')->pluck('id')->all();
    productMenuClick($page, 'button[wire\\:click^="reorderItemImages"]:not([disabled])');
    $page->wait(1);
    expect($item->galleryImages()->orderBy('sort_order')->pluck('id')->all())->not->toBe($galleryOrder);
    $primaryPath = $item->fresh()->image;
    productMenuClick($page, 'button[wire\\:click^="promoteItemImage"]');
    $page->wait(1);
    expect($item->fresh()->image)->not->toBe($primaryPath);

    foreach ([[320, 800], [390, 844], [768, 900], [1280, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
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
    foreach ([[320, 800], [390, 844], [768, 900], [1280, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
        $page->screenshot(false, "product-settings-{$width}x{$height}-dark");
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    productMenuAssertNoFailedResources($page);
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
    $page->keys('button[aria-label="'.__('menu.guest.close', [], 'lt').'"]', 'Shift+Tab');
    expect($page->script("document.activeElement?.closest('[role=dialog]') !== null"))->toBeTrue();
    $page->keys('[role="dialog"]', 'Escape')->assertNotPresent('[role="dialog"][aria-modal="true"]');
    expect($page->script('document.activeElement?.id'))->toBe('guest-menu-item-details-'.$item->id);
    $page->fill('input[wire\\:model\.live\.debounce\.250ms="search"]', 'Labai')->wait(1);
    productMenuClick($page, 'button[wire\\:click="$set(\'selectedCategoryId\', '.$category->id.')"]');
    $guestMenuComponentId = $page->script("document.querySelector('#guest-menu-language-{$branch->id}')?.closest('[data-component=guest-menu]')?.getAttribute('wire:id')");
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
    foreach ([[320, 800], [390, 844], [768, 900], [1280, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
        $page->screenshot(false, "product-guest-{$width}x{$height}");
    }
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

    foreach ([[320, 800], [390, 844], [768, 900], [1280, 900], [1440, 1000]] as [$width, $height]) {
        productMenuAssertNoOverflow($page, $width, $height);
        $page->screenshot(false, "product-{$role->value}-{$width}x{$height}-light");
    }

    $page->script("window.localStorage.setItem('flux.appearance', 'dark')");
    $page->navigate(route($routeName, absolute: false));
    productMenuAssertNoOverflow($page, 390, 844);
    $page->screenshot(false, "product-{$role->value}-390x844-dark");

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

function productMenuClick(PendingAwaitablePage $page, string $selector): void
{
    $encoded = json_encode($selector, JSON_THROW_ON_ERROR);
    expect($page->script("document.querySelector({$encoded})?.click(); true"))->toBeTrue();
}

function productMenuAttachPng(PendingAwaitablePage $page, string $selector, string $name): void
{
    $encodedSelector = json_encode($selector, JSON_THROW_ON_ERROR);
    $encodedName = json_encode($name, JSON_THROW_ON_ERROR);
    $attached = $page->script(<<<JAVASCRIPT
        (() => {
            const input = document.querySelector({$encodedSelector});
            if (!(input instanceof HTMLInputElement)) return false;
            const bytes = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAD0lEQVR42mP8z8AARMAgAAQAAf8CBZ0AAAAASUVORK5CYII='), character => character.charCodeAt(0));
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
