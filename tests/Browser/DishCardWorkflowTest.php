<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Support\MoneyFormatter;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create(['email' => 'dish.owner@example.test']);
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Šeimos virtuvė — Семейная кухня']);
    $this->branch = Branch::factory()->for($this->organization)->withDefaultSettings()->create(['name' => 'Old town restaurant', 'timezone' => 'UTC']);
    $this->menu = Menu::factory()->for($this->branch)->create(['name' => 'Seasonal menu', 'status' => MenuStatus::Active]);
    $this->category = MenuCategory::factory()->for($this->menu)->active()->create(['name' => 'Vegetable dishes']);
    $this->dish = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->withDistinctTranslations()->create(['name' => 'Garden vegetable soup', 'is_available' => true, 'price_cents' => 1250]);
    $this->dishUrl = route('organizations.brands.branches.menu.dish.edit', [$this->organization, $this->branch->brand, $this->branch, $this->dish], false);
});

test('one dish card retains its main draft through sections and history then saves and previews without an order', function (): void {
    $page = dishBrowserOwner($this->owner, $this->dishUrl.'?q=Garden&menu='.$this->menu->id);
    $prefix = '#edit-menu-item-'.$this->dish->id;
    $name = $prefix.'-panel-en input[type=text]';
    $page->assertAttribute($prefix.'-tab-en', 'aria-selected', 'true')->assertMissing($prefix.'-panel-ru');
    $page->assertVisible('[data-dish-section="main"]')->assertNotPresent('select[name="variantItemId"]')
        ->fill($name, 'Garden soup from one card');
    $page->click('[data-menu-section="photos"]')->assertQueryStringHas('section', 'photos')
        ->assertVisible('[data-dish-section="photos"]')->assertMissing('[data-dish-section="main"]')
        ->assertMissing('dialog[data-modal="menu-workspace-unsaved"]');
    expect($this->dish->fresh()->name)->toBe('Garden vegetable soup');
    $page->script('window.history.back()');
    $page->assertVisible('[data-dish-section="main"]')->assertValue($name, 'Garden soup from one card');
    $page->script('window.history.forward()');
    $page->assertVisible('[data-dish-section="photos"]')->assertQueryStringHas('section', 'photos');
    $page->click('[data-menu-section="main"]')->assertVisible('[data-dish-section="main"]')
        ->assertValue($name, 'Garden soup from one card');
    $page->assertAttribute($prefix.'-tab-en', 'aria-selected', 'true')->assertMissing($prefix.'-panel-ru')
        ->screenshot(false, 'dish-card-main-before-save');
    $page->click('form[wire\:submit="saveItem"] button[type=submit]')->assertVisible('[data-dish-saved]')
        ->assertPresent('[data-page="dish-card"]')->assertValue($name, 'Garden soup from one card');
    expect($this->dish->fresh()->name)->toBe('Garden soup from one card');

    $orderCount = DraftOrder::query()->count();
    $page->fill($name, 'Unsaved guest preview')->click('button[wire\:click="openPreview"]')
        ->assertVisible('[data-dish-preview-result]')->assertSeeIn('[data-dish-preview-result]', 'Garden soup from one card');
    $page->select('select[name="previewForm.source"]', 'draft')
        ->click('form[wire\:submit="refreshPreview"] button[type=submit]')
        ->assertSeeIn('[data-dish-preview-result]', 'Unsaved guest preview')
        ->assertSeeIn('[data-dish-preview-price]', MoneyFormatter::formatCents(1250, $this->branch->currency));
    expect($this->dish->fresh()->name)->toBe('Garden soup from one card')
        ->and(DraftOrder::query()->count())->toBe($orderCount);
    $page->click('a[href*="section=catalog"]')->assertVisible('dialog[data-modal="menu-workspace-unsaved"]');
    $page->click('button[x-on\:click="cancelNavigation"]')->assertValue($name, 'Unsaved guest preview');
    $page->click('button[wire\:click="discardMainChanges"]')->assertValue($name, 'Garden soup from one card');
    $page->click('a[href*="section=catalog"]')->assertPresent('[data-section="menu-catalog"]')
        ->assertQueryStringHas('q', 'Garden')->assertQueryStringHas('menu', (string) $this->menu->id)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('clean cross dish history restores the correct owner while dirty departure still needs confirmation', function (): void {
    $second = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->withDistinctTranslations()->create(['name' => 'Second dish identity', 'is_available' => true]);
    $second->translations()->where('language_code', 'en')->update(['name' => 'Second dish identity']);
    $page = dishBrowserOwner($this->owner, $this->dishUrl);
    $firstField = '#edit-menu-item-'.$this->dish->id.'-panel-en input[type=text]';
    $secondField = '#edit-menu-item-'.$second->id.'-panel-en input[type=text]';
    $page->click('a[href*="section=catalog"]')->assertPresent('[data-section="menu-catalog"]')
        ->click('article[wire\:key="menu-item-'.$second->id.'"] > div:first-child a[wire\:navigate]')
        ->assertValue($secondField, 'Second dish identity');
    $page->script('history.back()');
    $page->assertPresent('[data-section="menu-catalog"]')->assertNotPresent('[data-page="dish-card"]');
    $page->script('history.back()');
    $page->assertPathIs($this->dishUrl)->assertValue($firstField, 'Garden vegetable soup')
        ->assertNotPresent($secondField);
    $page->script('history.forward()');
    $page->assertPresent('[data-section="menu-catalog"]');
    $page->script('history.forward()');
    $page->assertValue($secondField, 'Second dish identity')->assertNotPresent($firstField)
        ->fill($secondField, 'Second unsaved draft');
    $page->script('history.back()');
    $page->assertVisible('dialog[data-modal="menu-workspace-unsaved"]')
        ->click('button[x-on\:click="cancelNavigation"]')
        ->assertValue($secondField, 'Second unsaved draft');
    $page->script('history.back()');
    $page->assertVisible('dialog[data-modal="menu-workspace-unsaved"]')
        ->click('button[x-on\:click="discardAndNavigate"]')
        ->assertPresent('[data-section="menu-catalog"]')->assertNotPresent('[data-page="dish-card"]')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($this->dish->fresh()->name)->toBe('Garden vegetable soup')
        ->and($second->fresh()->name)->toBe('Second dish identity');
});

test('dish details remain readable at five widths in both themes and two hundred percent zoom', function (string $locale): void {
    $longName = str_repeat('Daržovių sriuba — ', 8);
    $this->dish->update(['name' => $longName]);
    $this->dish->translations()->update(['name' => $longName, 'description' => str_repeat('Шviežios daržovės. Seasonal vegetables. ', 12)]);
    $page = dishBrowserOwner($this->owner, $this->dishUrl.'?lang='.$locale.'&language='.$locale);
    $prefix = '#edit-menu-item-'.$this->dish->id;
    $page->assertAttribute('html[lang]', 'lang', $locale)->assertAttribute($prefix.'-tab-'.$locale, 'aria-selected', 'true')
        ->assertScript("parseFloat(getComputedStyle(document.querySelector('.rm-dish')).rowGap) > 0", true)
        ->assertSee(__('dish.save_main', [], $locale));
    foreach (['light', 'dark'] as $theme) {
        $page->script("window.Flux.appearance = '{$theme}'");
        foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
            $page->resize($width, $height);
            foreach ([1, 2] as $zoom) {
                $page->script("document.documentElement.style.zoom = '{$zoom}'; document.querySelector('[data-dish-section=main]').scrollIntoView({ block: 'start' })");
                $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
                    ->assertScript("(() => { const rect = document.querySelector('[data-dish-section=main]').getBoundingClientRect(); return rect.left >= 0 && rect.right <= window.innerWidth; })()", true)
                    ->assertScript("Array.from(document.querySelectorAll('[data-dish-section=main] [data-locale-tab]')).every(element => element.scrollWidth <= element.clientWidth)", true)
                    ->assertScript("Array.from(document.querySelectorAll('.rm-dish [data-flux-badge]')).filter(element => element.checkVisibility()).every(element => element.scrollWidth <= element.clientWidth)", true);
                if ($width === 320 && $zoom === 2) {
                    $page->script("document.querySelector('#edit-menu-item-{$this->dish->id}-panel-{$locale} textarea').scrollIntoView({ block: 'center' })");
                    $page->assertScript("(() => { const field = document.querySelector('#edit-menu-item-{$this->dish->id}-panel-{$locale} textarea').getBoundingClientRect(); return field.top >= 0 && field.top < innerHeight; })()", true)
                        ->assertScript("document.querySelector('[data-flux-header]').getBoundingClientRect().bottom <= 0", true);
                }
                $page->screenshot(false, "dish-{$locale}-{$theme}-{$width}-zoom{$zoom}");
                if ($width === 390 && $zoom === 1) {
                    $page->script("document.querySelector('#edit-menu-item-{$this->dish->id}-tab-{$locale}').scrollIntoView({ block: 'center', inline: 'nearest' })");
                    $page->screenshot(false, "dish-{$locale}-{$theme}-language-tabs-390");
                }
            }
        }
    }
    $page->script("document.documentElement.style.zoom = '1'");
    $page->click($prefix.'-tab-lt')->keys($prefix.'-tab-lt', 'ArrowLeft')
        ->assertAttribute($prefix.'-tab-en', 'aria-selected', 'true')
        ->assertQueryStringHas('language', 'en')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['en', 'lt', 'ru']);

test('dish controls retain keyboard focus in forced colors and reduced motion', function (): void {
    $page = dishBrowserOwner($this->owner, $this->dishUrl, ['forcedColors' => 'active', 'reducedMotion' => 'reduce']);
    $prefix = '#edit-menu-item-'.$this->dish->id;
    $page->resize(390, 844)
        ->assertScript('matchMedia("(forced-colors: active)").matches', true)
        ->assertScript('matchMedia("(prefers-reduced-motion: reduce)").matches', true)
        ->keys($prefix.'-tab-en', 'ArrowRight')
        ->assertAttribute($prefix.'-tab-lt', 'aria-selected', 'true')
        ->assertScript('document.activeElement.id', 'edit-menu-item-'.$this->dish->id.'-tab-lt')
        ->assertScript("getComputedStyle(document.activeElement).outlineStyle !== 'none'", true)
        ->assertScript('document.activeElement.getBoundingClientRect().height >= 44', true)
        ->assertScript("(() => { const tab = document.activeElement.getBoundingClientRect(), strip = document.activeElement.closest('[role=tablist]').getBoundingClientRect(); return tab.left >= strip.left && tab.right <= strip.right; })()", true)
        ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
        ->screenshot(false, 'dish-forced-colors-reduced-motion-keyboard')
        ->fill($prefix.'-panel-lt input[type=text]', 'Keyboard draft')
        ->keys('[data-menu-section="photos"]', 'Enter')
        ->assertVisible('[data-dish-section="photos"]')
        ->assertMissing('dialog[data-modal="menu-workspace-unsaved"]')
        ->keys('[data-menu-section="main"]', 'Enter')
        ->assertValue($prefix.'-panel-lt input[type=text]', 'Keyboard draft')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($this->dish->translations()->where('language_code', 'lt')->value('name'))->not->toBe('Keyboard draft');
});

test('nested dish fields share the URL language and local offline cancellation preserves the child draft without replay', function (): void {
    $page = dishBrowserOwner($this->owner, $this->dishUrl.'?language=lt');
    $prefix = '#edit-menu-item-'.$this->dish->id;
    $page->fill($prefix.'-panel-lt input[type=text]', 'Nesaugotas patiekalas')
        ->click('[data-menu-section="variants"]')->assertVisible('[data-dish-section="variants"]')
        ->assertAttribute('[id="variant.variantTranslations-tab-lt"]', 'aria-selected', 'true')
        ->fill('[id="variant.variantTranslations-panel-lt"] input[type=text]', 'Nesaugota porcija')
        ->click('[id="variant.variantTranslations-tab-en"]')->assertQueryStringHas('language', 'en')
        ->click('[data-menu-section="main"]')->assertVisible('[data-dish-section="main"]')
        ->assertAttribute($prefix.'-tab-en', 'aria-selected', 'true')
        ->fill($prefix.'-panel-en input[type=text]', 'Offline abandoned dish');
    $page->click('[data-menu-section="photos"]')->assertVisible('[data-dish-section="photos"]')
        ->click('[data-menu-section="main"]')->assertVisible('[data-dish-section="main"]');
    $page->script('window.dishOfflineActions = []; window.Livewire.interceptMessage(({message, onSend}) => onSend(() => window.dishOfflineActions.push(...Array.from(message.actions).map(action => action.name))))');
    dishBrowserOffline($page, true);
    $page->assertDisabled('form[wire\:submit="saveItem"] button[type=submit]')
        ->click('button[wire\:click="discardMainChanges"]')
        ->assertValue($prefix.'-panel-en input[type=text]', 'Garden vegetable soup')
        ->assertScript('window.dishOfflineActions.length', 0);
    expect($this->dish->fresh()->name)->toBe('Garden vegetable soup');
    dishBrowserOffline($page, false);
    $page->assertEnabled('form[wire\:submit="saveItem"] button[type=submit]')
        ->assertScript('window.dishOfflineActions.length', 0)
        ->click('[data-menu-section="variants"]')->assertVisible('[data-dish-section="variants"]')
        ->click('[id="variant.variantTranslations-tab-lt"]')->assertQueryStringHas('language', 'lt')
        ->assertValue('[id="variant.variantTranslations-panel-lt"] input[type=text]', 'Nesaugota porcija')
        ->assertScript('window.dishOfflineActions.some(action => ["saveItem", "discardMainChanges", "createVariant"].includes(action))', false)
        ->click('a[href*="section=catalog"]')->assertVisible('dialog[data-modal="menu-workspace-unsaved"]')
        ->click('button[x-on\:click="cancelNavigation"]')
        ->assertValue('[id="variant.variantTranslations-panel-lt"] input[type=text]', 'Nesaugota porcija')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($this->dish->fresh()->name)->toBe('Garden vegetable soup')->and($this->dish->variants()->count())->toBe(0);
});

test('dish menu search reaches bounded results without making a draft until an option is chosen', function (): void {
    Menu::factory()->count(22)->for($this->branch)->sequence(fn ($sequence) => ['name' => 'A menu '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->create();
    $target = Menu::factory()->for($this->branch)->create(['name' => 'Z searchable dinner']);
    $targetCategory = MenuCategory::factory()->for($target)->active()->create(['name' => 'Evening dishes']);
    $page = dishBrowserOwner($this->owner, $this->dishUrl);
    $select = 'ui-select[wire\:model\.live="editingItemForm.itemMenuId"]';
    $page->click($select.' [data-flux-select-button]')
        ->assertNotPresent($select.' ui-option[value="'.$target->id.'"]')
        ->fill('input[wire\:model\.live\.debounce\.250ms="search.menu"]', 'Z searchable')
        ->assertVisible($select.' ui-option[value="'.$target->id.'"]');
    $page->script("document.querySelector('[data-dish-heading]').click()");
    $page->click('a[href*="section=catalog"]')->assertPresent('[data-section="menu-catalog"]')
        ->assertMissing('dialog[data-modal="menu-workspace-unsaved"]');
    $page->navigate($this->dishUrl)->assertPresent('[data-page="dish-card"]');
    $page->script(<<<'JAVASCRIPT'
        window.dishMenuDepartures = [];
        window.addEventListener('livewire:navigate', event => {
            const shell = Alpine.$data(document.querySelector('[data-workspace-navigation]'));
            const attempt = { pending: shell.pending };
            window.dishMenuDepartures.push(attempt);
            queueMicrotask(() => { attempt.blocked = event.defaultPrevented && shell.blocked; });
        }, { capture: true });
        JAVASCRIPT);
    $page
        ->click($select.' [data-flux-select-button]')
        ->fill('input[wire\:model\.live\.debounce\.250ms="search.menu"]', 'Z searchable')
        ->click($select.' ui-option[value="'.$target->id.'"]')
        ->assertSeeIn($select.' [data-flux-select-button]', $target->name)
        ->assertPresent('ui-select[wire\:model="editingItemForm.itemCategoryId"] ui-option[value="'.$targetCategory->id.'"]')
        ->click('a[href*="section=catalog"]');
    $page->assertScript('window.dishMenuDepartures.length', 1);
    if ($page->script('window.dishMenuDepartures[0].pending > 0')) {
        $page->assertScript('window.dishMenuDepartures[0].blocked', true)
            ->assertPresent('[data-page="dish-card"]')
            ->assertSeeIn($select.' [data-flux-select-button]', $target->name)
            ->assertScript('Alpine.$data(document.querySelector("[data-page=dish-card]")).hasUnsavedChanges()', true)
            ->assertScript('Alpine.$data(document.querySelector("[data-workspace-navigation]")).pending', 0)
            ->assertScript('window.dishMenuDepartures.length', 1)
            ->click('a[href*="section=catalog"]');
    }
    $page->assertVisible('dialog[data-modal="menu-workspace-unsaved"]')
        ->click('button[x-on\:click="cancelNavigation"]')->assertPresent('[data-page="dish-card"]')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($this->dish->fresh()->menu_id)->toBe($this->menu->id);
});

test('one dish gains a variant and modifier and previews the same price as an independent guest without changing its historical draft', function (): void {
    $page = dishBrowserOwner($this->owner, $this->dishUrl);
    $prefix = '#edit-menu-item-'.$this->dish->id;
    $page->fill($prefix.'-panel-en input[type=text]', 'Main draft kept through configuration')
        ->click('[data-menu-section="variants"]')->assertVisible('[data-dish-section="variants"]')
        ->assertNotPresent('select[name="variantItemId"]');
    dishBrowserNames($page, 'variant.variantTranslations', ['en' => 'Large bowl', 'lt' => 'Didelis dubuo', 'ru' => 'Большая порция']);
    $page->fill('input[name="variant.variantPrice"]', '16.00')
        ->click('form[wire\:submit="createVariant"] button[type=submit]')
        ->assertSeeIn('[data-dish-section="variants"] article[wire\:key^="menu-item-variant-"] span.font-semibold', 'Large bowl');
    $variant = $this->dish->variants()->sole();
    expect($variant->price_cents)->toBe(1600)->and($this->dish->fresh()->price_cents)->toBe(1250);
    $page->click('[data-menu-section="modifiers"]')->assertVisible('[data-dish-section="modifiers"]')
        ->assertNotPresent('select[name="assignment.modifierItemId"]');
    dishBrowserNames($page, 'create-modifier-group', ['en' => 'Soup toppings', 'lt' => 'Sriubos priedai', 'ru' => 'Добавки для супа']);
    $page->click('form[wire\:submit="createModifierGroup"] button[type=submit]')
        ->assertSeeIn('[data-dish-section="modifiers"] div[wire\:key^="modifier-group-"] h2', 'Soup toppings');
    $group = ModifierGroup::query()->where('branch_id', $this->branch->id)->sole();
    expect($this->dish->modifierGroups()->whereKey($group->id)->exists())->toBeTrue();
    dishBrowserNames($page, 'create-modifier-option', ['en' => 'Fresh herbs', 'lt' => 'Šviežios žolelės', 'ru' => 'Свежая зелень']);
    $page->fill('input[name="option.modifierOptionPriceDelta"]', '1.50')
        ->click('form[wire\:submit="createModifierOption"] button[type=submit]')
        ->assertValue('[id="create-modifier-option-panel-ru"] input[type=text]', '')
        ->assertSeeIn('[data-dish-section="modifiers"] div[wire\:key^="modifier-option-"] span.font-medium', 'Fresh herbs');
    $option = ModifierOption::query()->where('modifier_group_id', $group->id)->sole();
    $page->click('[data-menu-section="main"]')->assertVisible('[data-dish-section="main"]')
        ->click($prefix.'-tab-en')->assertValue($prefix.'-panel-en input[type=text]', 'Main draft kept through configuration')
        ->click('button[wire\:click="openPreview"]')->assertVisible('[data-dish-preview-result]')
        ->assertMissing('[data-dish-preview-price]')
        ->select('select[name="previewForm.variantId"]', (string) $variant->id)
        ->click('[data-menu-preview] [data-flux-checkbox][value="'.$option->id.'"]')
        ->click('form[wire\:submit="refreshPreview"] button[type=submit]')
        ->assertSeeIn('[data-dish-preview-price]', MoneyFormatter::formatCents(1750, $this->branch->currency));
    expect($this->dish->fresh()->name)->toBe('Garden vegetable soup')->and(DraftOrder::query()->count())->toBe(0);

    $point = ServicePoint::factory()->for($this->branch)->create(['is_active' => true]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $session = TableSession::factory()->forServicePoint($point)->waiterOpened()->active()->create();
    $guestIdentity = TableSessionGuest::factory()->for($session)->active()->create(['locale' => 'en']);
    $draft = DraftOrder::factory()->for($session)->create();
    $line = DraftOrderItem::factory()->for($draft)->create(['table_session_guest_id' => $guestIdentity->id, 'menu_item_id' => $this->dish->id, 'item_name' => 'Historical soup', 'unit_price_cents' => 900, 'total_price_cents' => 900]);
    $this->withCookie('guest_token_'.substr(hash('sha256', $qr->public_token), 0, 24), $guestIdentity->guest_token);
    $guest = visit(route('public.qr.show', ['token' => $qr->public_token], false));
    $guest->click('#guest-menu-item-details-'.$this->dish->id)
        ->click('input[type=radio][value="'.$variant->id.'"]')
        ->click('button[wire\:click="toggleModifierOption('.$group->id.', '.$option->id.')"]')
        ->assertSeeIn('button[wire\:click="saveConfiguredItem"]', MoneyFormatter::formatCents(1750, $this->branch->currency))
        ->assertDontSee('Main draft kept through configuration')->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($line->fresh()->unit_price_cents)->toBe(900)->and($line->fresh()->item_name)->toBe('Historical soup')
        ->and(DraftOrderItem::query()->count())->toBe(1)->and(DraftOrder::query()->count())->toBe(1);
    $page->assertValue($prefix.'-panel-en input[type=text]', 'Main draft kept through configuration')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

/** @param array<string, string> $options */
function dishBrowserOwner(User $owner, string $url, array $options = []): PendingAwaitablePage
{
    $page = visit(route('login', absolute: false), $options);
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->assertScript('document.readyState', 'complete');
    $page->navigate($url)->assertPresent('[data-page="dish-card"]')
        ->assertScript("getComputedStyle(document.querySelector('.rm-dish')).display", 'grid');

    return $page;
}

function dishBrowserOffline(PendingAwaitablePage $page, bool $offline): void
{
    $guid = (new ReflectionProperty($page->page()->context(), 'guid'))->getValue($page->page()->context());
    expect($guid)->toBeString();
    foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
        // Consume the protocol response before checking offline browser behaviour.
    }
}

/** @param array<string, string> $names */
function dishBrowserNames(PendingAwaitablePage $page, string $prefix, array $names): void
{
    foreach ($names as $locale => $name) {
        $page->click('[id="'.$prefix.'-tab-'.$locale.'"]')->assertAttribute('[id="'.$prefix.'-tab-'.$locale.'"]', 'aria-selected', 'true')
            ->fill('[id="'.$prefix.'-panel-'.$locale.'"] input[type=text]', $name);
    }
}
