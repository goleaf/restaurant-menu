<?php

declare(strict_types=1);

use App\Enums\DraftOrderStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;

test('new owner can onboard and close a fully paid table through the browser', function () {
    $this->withVite();
    $this->seed(SystemPermissionsSeeder::class);

    $registeredOwner = User::factory()->create([
        'name' => 'Browser E2E Owner',
        'email' => 'browser-e2e-owner@example.test',
    ]);

    $page = visit(route('login', absolute: false));

    $page
        ->assertSee(__('ui.auth.login.log_in_to_your_account'))
        ->fill('email', 'browser-e2e-owner@example.test')
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->assertNoJavaScriptErrors();

    expect($registeredOwner->email)->toBe('browser-e2e-owner@example.test');

    completeBrowserRestaurantOnboarding($page, $registeredOwner);

    $servicePoint = ServicePoint::query()
        ->select(['id', 'status'])
        ->sole();
    $qrCode = QrCode::query()
        ->select(['id', 'service_point_id', 'public_token'])
        ->sole();
    $menuItem = MenuItem::query()
        ->select(['id', 'name', 'price_cents'])
        ->where('name', 'Browser E2E Pasta')
        ->sole();

    expect($menuItem->price_cents)->toBe(850)
        ->and($qrCode->service_point_id)->toBe($servicePoint->id);

    MenuItemVariant::factory()
        ->for($menuItem, 'item')
        ->portion()
        ->default()
        ->create([
            'name' => 'Regular portion',
            'price_cents' => 850,
            'sort_order' => 10,
        ]);
    $largePortion = MenuItemVariant::factory()
        ->for($menuItem, 'item')
        ->portion()
        ->create([
            'name' => 'Large portion',
            'price_cents' => 1250,
            'sort_order' => 20,
        ]);

    $page
        ->navigate(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertSee('Browser E2E Bistro')
        ->assertSee('Browser E2E Table 1')
        ->fill('guest_name', 'Browser Guest');
    clickBrowserElement($page, 'form[wire\\:submit="enterTable"] button[type="submit"]');
    $page
        ->assertSee(__('guest.table.welcome_name', ['name' => 'Browser Guest']))
        ->assertNoJavaScriptErrors();

    $tableSession = TableSession::query()
        ->select(['id', 'service_point_id', 'status'])
        ->where('service_point_id', $servicePoint->id)
        ->sole();
    $guest = TableSessionGuest::query()
        ->select(['id', 'table_session_id', 'guest_name', 'ready_at'])
        ->where('table_session_id', $tableSession->id)
        ->sole();

    expect($guest->guest_name)->toBe('Browser Guest')
        ->and($guest->table_session_id)->toBe($tableSession->id);

    clickBrowserElement($page, sprintf('button[wire\\:click="openItem(%d)"]', $menuItem->id));
    $page
        ->assertSee('Browser E2E Pasta')
        ->assertSee(__('menu.variants.guest.choose'))
        ->assertSee('Large portion')
        ->fill('textarea[wire\\:model="itemComment"]', 'Browser E2E order');
    clickBrowserElement($page, sprintf('input[wire\\:model\\.live="selectedItemVariantId"][value="%d"]', $largePortion->id));
    $page->assertSee('€12.50');
    clickBrowserElement($page, 'button[wire\\:click="saveConfiguredItem"]');
    $page->assertSee(__('menu.guest.item_added'));
    assertBrowserCalloutContrast($page);
    $page->navigate(route('public.qr.show', ['token' => $qrCode->public_token], false));
    $editSelector = 'button[wire\\:click^="editItem("]';
    $page->assertPresent($editSelector);
    $page->script('document.querySelector('.json_encode($editSelector, JSON_THROW_ON_ERROR).').focus()');
    clickBrowserElement($page, $editSelector);
    $page
        ->assertVisible('dialog[data-modal="guest-draft-item"]')
        ->assertScript('document.getElementById(document.querySelector("dialog[data-modal=guest-draft-item]").getAttribute("aria-labelledby"))?.textContent.trim()', $menuItem->name)
        ->assertScript('document.activeElement.closest("dialog")?.dataset.modal === "guest-draft-item"');
    $page->keys('dialog[data-modal="guest-draft-item"] [autofocus]', 'Escape');
    $page
        ->assertMissing('dialog[data-modal="guest-draft-item"][open]')
        ->assertScript('document.activeElement.getAttribute("wire:click")?.startsWith("editItem(") === true');
    clickBrowserElement($page, 'button[wire\\:click="toggleReadyStatus"]');
    $page->assertSee(__('guest.table.ready_feedback'));

    expect($guest->fresh()->ready_at)->not->toBeNull();

    clickBrowserElement($page, 'button[wire\\:click="sendDraftToWaiter"]');
    $page
        ->assertSee(__('guest.table.sent_to_waiter'))
        ->assertNoJavaScriptErrors();

    $draftOrder = DraftOrder::query()
        ->select(['id', 'table_session_id', 'status'])
        ->where('table_session_id', $tableSession->id)
        ->sole();

    expect($draftOrder->status)->toBe(DraftOrderStatus::SentToWaiter)
        ->and($draftOrder->items()->count())->toBe(1)
        ->and($draftOrder->items()->sole()->menu_item_variant_id)->toBe($largePortion->id)
        ->and($draftOrder->items()->sole()->variant_name)->toBe('Large portion')
        ->and($draftOrder->items()->sole()->total_price_cents)->toBe(1250)
        ->and($guest->fresh()->ready_at)->toBeNull();

    $waiterTablePath = route('restaurant.waiter.tables.show', ['tableSession' => $tableSession], false);

    $page
        ->navigate($waiterTablePath)
        ->assertSee('Browser E2E Pasta');
    clickBrowserElement($page, 'button[wire\\:click="confirmDraft"]');
    $page
        ->assertSee(__('ui.livewire.waiter.tabledetail.zakaz_podtverzden_oficiantom_kuxnia_i_bar_po'))
        ->assertNoJavaScriptErrors();

    $order = Order::query()
        ->select(['id', 'draft_order_id', 'status', 'total_price_cents'])
        ->where('draft_order_id', $draftOrder->id)
        ->sole();

    expect($order->status)->toBe(OrderStatus::SentToKitchenBar)
        ->and($order->total_price_cents)->toBe(1250)
        ->and($order->items()->sole()->variant_name)->toBe('Large portion');

    $ticketItem = KitchenTicketItem::query()
        ->whereHas('kitchenTicket', fn ($query) => $query->where('order_id', $order->id))
        ->sole();

    $page
        ->navigate(route('restaurant.kitchen.dashboard', absolute: false))
        ->assertSee('Browser E2E Pasta');
    clickBrowserElement($page, 'button[wire\\:click="setItemStatus('.$ticketItem->id.', \'accepted\')"]');
    $page->assertPresent('[data-ticket-item-status="accepted"]');
    clickBrowserElement($page, 'button[wire\\:click="setItemStatus('.$ticketItem->id.', \'in_progress\')"]');
    $page->assertPresent('[data-ticket-item-status="in_progress"]');
    clickBrowserElement($page, 'button[wire\\:click="setItemStatus('.$ticketItem->id.', \'ready\')"]');
    $page
        ->assertPresent('[data-ticket-item-status="ready"]')
        ->assertSee(__('statuses.kitchen_ticket_item.ready'))
        ->assertNoJavaScriptErrors();

    $page
        ->navigate($waiterTablePath)
        ->assertSee(__('ui.waiter.dashboard.mark_served'));
    clickBrowserElement($page, 'button[wire\\:click="markTicketItemServed('.$ticketItem->id.')"]');
    $page
        ->assertSee(__('ui.waiter.table_detail.served_at'))
        ->assertNoJavaScriptErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Served);

    $page
        ->navigate(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertSee('Browser E2E Pasta');
    clickBrowserElement($page, 'button[wire\\:click="requestBill"]');
    $page
        ->assertSee(__('guest.table.bill_requested'))
        ->assertNoJavaScriptErrors();

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::PaymentRequested)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::PaymentRequested);

    $page
        ->navigate($waiterTablePath)
        ->assertSee('€12.50')
        ->select('select[wire\\:model="paymentMethod"]', ManualPaymentMethod::CardTerminal->value)
        ->fill('paymentNote', 'Browser E2E card payment');
    clickBrowserButtonContaining($page, __('payments.pay_whole_table').' · €12.50');
    clickBrowserElement($page, 'button[wire\\:click="recordTablePayment"]');
    $page
        ->assertSee(__('payments.messages.payment_recorded'))
        ->assertSee(__('payments.fully_paid'))
        ->assertNoJavaScriptErrors();

    $payment = ManualPayment::query()
        ->select(['id', 'table_session_id', 'scope', 'payment_method', 'amount_cents'])
        ->where('table_session_id', $tableSession->id)
        ->sole();

    expect($payment->scope)->toBe(ManualPaymentScope::Table)
        ->and($payment->payment_method)->toBe(ManualPaymentMethod::CardTerminal)
        ->and($payment->amount_cents)->toBe(1250)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Paid)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);

    clickBrowserElement($page, '#close-table button');
    clickBrowserElement($page, 'button[wire\\:click="closeTableSession"]');
    $page
        ->assertSee(__('payments.messages.session_closed'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed)
        ->and($tableSession->fresh()->ended_at)->not->toBeNull()
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
        ->and($order->fresh()->status)->toBe(OrderStatus::Closed);
});

function completeBrowserRestaurantOnboarding(PendingAwaitablePage $page, User $registeredOwner): void
{
    foreach (['lt', 'ru', 'en'] as $locale) {
        assertBrowserOnboardingLocaleLayout($page, $registeredOwner, $locale);
        foreach ([[320, 720], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
            assertBrowserHasNoHorizontalOverflow($page, $width, $height);
        }
        assertBrowserOnboardingTextZoomReflow($page);
    }
    $page->navigate(route('onboarding.restaurant', ['lang' => 'en'], false))->resize(1440, 1000)
        ->assertSee(__('center.title'))->assertVisible('nav[aria-label="'.__('center.setup_groups').'"]');
    assertBrowserDarkThemeLayout($page);
    assertBrowserKeyboardFocusIsVisible($page);
    $page->fill('input[wire\\:model="form.branchName"]', 'Browser E2E Bistro Old Town')
        ->fill('input[wire\\:model="form.branchAddress"]', 'Pilies 1')
        ->fill('input[wire\\:model="form.branchCity"]', 'Vilnius')
        ->fill('input[wire\\:model="form.organizationName"]', 'Browser E2E Food Group')
        ->fill('input[wire\\:model="form.brandName"]', 'Browser E2E Bistro');
    browserSetupChoose($page, 'form.branchTimezone', 'Europe/Vilnius');
    browserSetupChoose($page, 'form.branchCurrency', 'EUR');
    clickBrowserElement($page, 'form[wire\\:submit="createRestaurant"] button[type="submit"]');
    $page->assertPresent('ui-select[wire\\:model="form.branchCountryCode"] button[data-invalid]')
        ->assertScript('document.activeElement?.closest("ui-select")?.getAttribute("wire:model")', 'form.branchCountryCode');
    browserSetupChoose($page, 'form.branchCountryCode', 'LT');
    clickBrowserElement($page, 'form[wire\\:submit="createRestaurant"] button[type="submit"]');
    $page->assertVisible('#restaurant-setup-group-2');
    $setup = RestaurantOnboarding::query()->where('user_id', $registeredOwner->id)->sole();
    expect($setup->branch->is_active)->toBeFalse()->and($setup->completed_at)->toBeNull();
    $page->navigate(route('restaurants.setup', ['setup' => $setup->id, 'step' => 3], false))
        ->assertVisible('#restaurant-setup-group-3')
        ->fill('input[wire\\:model="form.menuName"]', 'Browser E2E Menu')
        ->fill('input[wire\\:model="form.categoryName"]', 'Browser E2E Main')
        ->fill('input[wire\\:model="form.itemName"]', 'Browser E2E Pasta')
        ->fill('input[wire\\:model="form.itemPrice"]', '8.50');
    clickBrowserElement($page, 'form[wire\\:submit="createStarterMenu"] button[type="submit"]');
    $page->assertSee('Browser E2E Menu');
    expect($setup->fresh()->menu->status)->toBe(MenuStatus::Draft)
        ->and($setup->fresh()->menuItem->is_available)->toBeFalse()->and(ServicePoint::query()->count())->toBe(0);
    clickBrowserElement($page, 'nav button[wire\\:click="goToStep(2)"]');
    $page->fill('input[wire\\:model="form.areaName"]', 'Browser E2E Hall');
    clickBrowserElement($page, 'form[wire\\:submit="createArea"] button[type="submit"]');
    $page->fill('input[wire\\:model="form.tableCount"]', '1')
        ->fill('input[wire\\:model="form.tablePrefix"]', 'Browser E2E Table')
        ->fill('input[wire\\:model="form.tableCapacity"]', '4');
    clickBrowserElement($page, 'form[wire\\:submit="createServicePoints"] button[type="submit"]');
    $page->assertSee(__('center.tables_saved', ['count' => 1]));
    $page->navigate(route('restaurants.setup', ['setup' => $setup->id, 'step' => 2], false));
    clickBrowserElement($page, 'button[wire\\:click="generateQrCodes"]');
    $page->assertMissing('button[wire\\:click="generateQrCodes"]');
    clickBrowserElement($page, 'nav button[wire\\:click="goToStep(4)"]');
    clickBrowserElement($page, 'button[wire\\:click="complete"]');
    $page->assertSee(__('center.completed_history'))->assertNoJavaScriptErrors();
    $setup->refresh();
    expect($setup->completed_at)->not->toBeNull()->and($setup->branch->is_active)->toBeFalse()
        ->and($setup->menu->status)->toBe(MenuStatus::Draft)->and($setup->menuItem->is_available)->toBeFalse();

    // Publication is a separate, explicit user operation after preparation.
    $page->navigate(route('restaurants.index', ['kind' => 'branch', 'object' => $setup->branch_id], false));
    clickBrowserElement($page, 'ui-checkbox[wire\\:model="form.isActive"]');
    clickBrowserElement($page, 'form[wire\\:submit="save"] button[type="submit"]');
    $page->assertSee(__('center.saved'));
    expect($setup->branch->fresh()->is_active)->toBeTrue();
    $scope = [$setup->organization_id, $setup->brand_id, $setup->branch_id];
    $page->navigate(route('organizations.brands.branches.menu.index', $scope, false));
    clickBrowserElement($page, 'button[wire\\:click="startEditingMenu('.$setup->menu_id.')"]');
    $page->click('#edit-menu-'.$setup->menu_id.'-tab-lt')
        ->fill('input[wire\\:model="editingMenuForm.menuTranslations.lt"]', 'Naršyklės bandymo meniu')
        ->click('#edit-menu-'.$setup->menu_id.'-tab-ru')
        ->fill('input[wire\\:model="editingMenuForm.menuTranslations.ru"]', 'Меню браузерного сценария');
    $page->select('select[wire\\:model="editingMenuForm.menuStatus"]', 'active');
    clickBrowserElement($page, 'form[wire\\:submit="updateMenu"] button[type="submit"]');
    $page->assertSee(__('ui.livewire.organizations.brands.branches.menu.index.menu_updated'));
    $page->navigate(route('organizations.brands.branches.availability.index', [...$scope, 'section' => 'stoplist'], false));
    clickBrowserElement($page, 'ui-checkbox[wire\\:model="selectedItems"][value="'.$setup->menu_item_id.'"]');
    clickBrowserElement($page, 'button[wire\\:click="openBulk"]');
    $page->select('select[name="restriction.operation"]', 'resume');
    clickBrowserElement($page, 'form[wire\\:submit="previewRestriction"] button[type="submit"]');
    $page->assertVisible('[data-availability-preview]');
    clickBrowserElement($page, 'button[wire\\:click="applyRestriction"]');
    $page->assertSee(__('availability.applied'))->assertNoJavaScriptErrors();
    expect($setup->menu->fresh()->status)->toBe(MenuStatus::Active)
        ->and($setup->menuItem->fresh()->is_available)->toBeTrue();
}

function browserSetupChoose(PendingAwaitablePage $page, string $model, string $value): void
{
    $selector = 'ui-select[wire\\:model="'.$model.'"]';
    $page->click($selector.' button[role="combobox"]')->click($selector.' ui-option[value="'.$value.'"]');
}

function assertBrowserHasNoHorizontalOverflow(PendingAwaitablePage $page, int $width, int $height): void
{
    $currentPath = (string) $page->script('window.location.pathname + window.location.search');

    $page->resize($width, $height);
    $page->navigate($currentPath);

    $page->assertPresent($width < 1024
        ? '[data-flux-sidebar][data-flux-sidebar-on-mobile]'
        : '[data-flux-sidebar][data-flux-sidebar-on-desktop]');

    $overflowState = $page->script(<<<'JAVASCRIPT'
        (() => {
            const root = document.documentElement;
            const offenders = [...document.querySelectorAll('body *')]
                .filter((element) => {
                    const style = getComputedStyle(element);
                    const rectangle = element.getBoundingClientRect();

                    return style.display !== 'none'
                        && style.position !== 'fixed'
                        && (rectangle.left < -1 || rectangle.right > root.clientWidth + 1);
                })
                .slice(0, 8)
                .map((element) => {
                    const rectangle = element.getBoundingClientRect();

                    return {
                        element: element.tagName.toLowerCase(),
                        className: String(element.className).slice(0, 160),
                        left: Math.round(rectangle.left),
                        right: Math.round(rectangle.right),
                        width: Math.round(rectangle.width),
                    };
                });

            return {
                clientWidth: root.clientWidth,
                scrollWidth: root.scrollWidth,
                offenders,
                layout: ['body', '[data-flux-header]', '[data-flux-main]', '[data-flux-sidebar]']
                    .map((selector) => {
                        const element = document.querySelector(selector);

                        if (!(element instanceof HTMLElement)) {
                            return { selector, missing: true };
                        }

                        const rectangle = element.getBoundingClientRect();

                        return {
                            selector,
                            left: Math.round(rectangle.left),
                            right: Math.round(rectangle.right),
                            width: Math.round(rectangle.width),
                            display: getComputedStyle(element).display,
                        };
                    }),
            };
        })()
    JAVASCRIPT);

    expect($overflowState['scrollWidth'])->toBeLessThanOrEqual(
        $overflowState['clientWidth'],
        "Restaurant onboarding overflows horizontally at {$width}x{$height}: ".json_encode([
            'layout' => $overflowState['layout'],
            'offenders' => $overflowState['offenders'],
        ], JSON_THROW_ON_ERROR),
    );
}

function assertBrowserOnboardingLocaleLayout(
    PendingAwaitablePage $page,
    User $registeredOwner,
    string $locale,
): void {
    $page->navigate(route('profile.edit', absolute: false));
    $page->select('select[wire\\:model="locale"]', $locale);
    clickBrowserElement($page, 'form[wire\\:submit="updateProfileInformation"] button[type="submit"]');
    $page->assertSee(__('ui.livewire.settings.profile.profile_updated', [], $locale));

    expect($registeredOwner->refresh()->locale)->toBe($locale);

    $page->navigate(route('onboarding.restaurant', absolute: false));

    $page
        ->assertAttribute('html:root', 'lang', $locale)
        ->assertVisible('h1:first-of-type');

    expect(trim((string) $page->text('h1:first-of-type')))->not->toBe('');

    assertBrowserHasNoHorizontalOverflow($page, 320, 720);
    assertBrowserHasNoHorizontalOverflow($page, 1440, 1000);
}

function assertBrowserDarkThemeLayout(PendingAwaitablePage $page): void
{
    $page->assertScript("getComputedStyle(document.body).backgroundColor !== 'rgba(0, 0, 0, 0)'");
    $styleState = $page->script(<<<'JAVASCRIPT'
        ({
            canvas: getComputedStyle(document.body).backgroundColor,
            links: [...document.querySelectorAll('link[rel="stylesheet"]')].map((link) => link.href),
            sheets: [...document.styleSheets].map((sheet) => sheet.href),
        })
    JAVASCRIPT);

    $styleSummary = sprintf(
        'links=%d first_link=%s sheets=%d first_sheet=%s',
        count($styleState['links']),
        $styleState['links'][0] ?? 'none',
        count($styleState['sheets']),
        $styleState['sheets'][0] ?? 'none',
    );

    expect($styleState['canvas'])->not->toBe('', $styleSummary);

    $lightCanvas = $styleState['canvas'];

    $page->script("window.Flux.appearance = 'dark'");
    $page->resize(390, 844);
    $page->navigate(route('onboarding.restaurant', absolute: false));
    $page->assertScript("document.documentElement.classList.contains('dark') && getComputedStyle(document.body).backgroundColor !== 'rgba(0, 0, 0, 0)'");

    $darkCanvas = $page->script('getComputedStyle(document.body).backgroundColor');

    expect($darkCanvas)->not->toBe($lightCanvas);

    assertBrowserHasNoHorizontalOverflow($page, 390, 844);

    $page->script("window.Flux.appearance = 'light'");
    $page->navigate(route('onboarding.restaurant', absolute: false));
}

function assertBrowserKeyboardFocusIsVisible(PendingAwaitablePage $page): void
{
    $page->keys('[data-page="restaurant-setup"]', 'Tab');

    $focusState = $page->script(<<<'JAVASCRIPT'
        (() => {
            const activeElement = document.activeElement;

            return {
                isBody: activeElement === document.body,
                isFocusVisible: activeElement instanceof HTMLElement && activeElement.matches(':focus-visible'),
            };
        })()
    JAVASCRIPT);

    expect($focusState)->toMatchArray([
        'isBody' => false,
        'isFocusVisible' => true,
    ]);
}

function assertBrowserOnboardingTextZoomReflow(PendingAwaitablePage $page): void
{
    $currentPath = (string) $page->script('window.location.pathname + window.location.search');

    $page
        ->resize(390, 844)
        ->navigate($currentPath)
        ->assertPresent('[data-flux-sidebar][data-flux-sidebar-on-mobile]');
    $overflowState = $page->script(<<<'JAVASCRIPT'
        (() => {
            document.documentElement.style.fontSize = '200%';

            const root = document.documentElement;
            const offenders = [...document.querySelectorAll('body *')]
                .filter((element) => {
                    const rectangle = element.getBoundingClientRect();
                    const style = getComputedStyle(element);

                    return style.display !== 'none'
                        && style.position !== 'fixed'
                        && rectangle.right > root.clientWidth + 1;
                })
                .slice(0, 8)
                .map((element) => {
                    const rectangle = element.getBoundingClientRect();

                    return {
                        element: element.tagName.toLowerCase(),
                        className: String(element.className).slice(0, 160),
                        clientWidth: element.clientWidth,
                        scrollWidth: element.scrollWidth,
                        left: Math.round(rectangle.left),
                        right: Math.round(rectangle.right),
                        width: Math.round(rectangle.width),
                        text: element.textContent.replace(/\s+/g, ' ').trim().slice(0, 80),
                    };
                });

            const result = {
                clientWidth: root.clientWidth,
                scrollWidth: root.scrollWidth,
                bodyClientWidth: document.body.clientWidth,
                bodyScrollWidth: document.body.scrollWidth,
                offenders,
                internallyOverflowing: [...document.querySelectorAll('body *')]
                    .filter((element) => element.scrollWidth > element.clientWidth + 1)
                    .slice(0, 8)
                    .map((element) => ({
                        element: element.tagName.toLowerCase(),
                        className: String(element.className).slice(0, 160),
                        clientWidth: element.clientWidth,
                        scrollWidth: element.scrollWidth,
                    })),
            };

            window.scrollTo({ left: root.scrollWidth, behavior: 'instant' });
            result.maximumScrollX = window.scrollX;
            window.scrollTo({ left: 0, behavior: 'instant' });

            document.documentElement.style.removeProperty('font-size');

            return result;
        })()
    JAVASCRIPT);

    expect($overflowState['scrollWidth'])->toBeLessThanOrEqual(
        $overflowState['clientWidth'],
        'Restaurant onboarding does not reflow at 200% text size: '.json_encode($overflowState, JSON_THROW_ON_ERROR),
    );
}

function assertBrowserCalloutContrast(PendingAwaitablePage $page): void
{
    $contrast = $page->script(<<<'JAVASCRIPT'
        (() => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d', { willReadFrequently: true });
            const rgb = color => {
                context.clearRect(0, 0, 1, 1);
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);
                return [...context.getImageData(0, 0, 1, 1).data];
            };
            const luminance = color => color.slice(0, 3).map(channel => {
                const value = channel / 255;
                return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
            }).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
            return [...document.querySelectorAll('[data-flux-callout] [data-slot="heading"], [data-flux-callout] [data-slot="text"]')]
                .filter(element => element.getClientRects().length && element.textContent.trim())
                .map(element => {
                    const ancestors = [];
                    for (let parent = element; parent; parent = parent.parentElement) ancestors.unshift(parent);
                    const background = ancestors.reduce((base, parent) => {
                        const color = rgb(getComputedStyle(parent).backgroundColor);
                        return base.map((channel, index) => channel * (1 - color[3] / 255) + color[index] * color[3] / 255);
                    }, [255, 255, 255]);
                    const foreground = luminance(rgb(getComputedStyle(element).color));
                    const surface = luminance(background);
                    return (Math.max(foreground, surface) + 0.05) / (Math.min(foreground, surface) + 0.05);
                });
        })()
    JAVASCRIPT);

    expect($contrast)->not->toBeEmpty();
    foreach ($contrast as $ratio) {
        expect($ratio)->toBeGreaterThanOrEqual(4.5);
    }
}

function clickBrowserElement(PendingAwaitablePage $page, string $selector): void
{
    $encodedSelector = json_encode($selector, JSON_THROW_ON_ERROR);
    $clicked = $page->script(<<<JAVASCRIPT
        (() => {
            const element = document.querySelector({$encodedSelector});

            if (!(element instanceof HTMLElement)) {
                return false;
            }

            element.click();

            return true;
        })()
    JAVASCRIPT);

    expect($clicked)->toBeTrue();
}

function clickBrowserButtonContaining(PendingAwaitablePage $page, string $text): void
{
    $encodedText = json_encode($text, JSON_THROW_ON_ERROR);
    $clicked = $page->script(<<<JAVASCRIPT
        (() => {
            const expectedText = {$encodedText};
            const button = [...document.querySelectorAll('button')]
                .find((element) => element.textContent.replace(/\\s+/g, ' ').trim().includes(expectedText));

            if (!(button instanceof HTMLButtonElement)) {
                return false;
            }

            button.click();

            return true;
        })()
    JAVASCRIPT);

    expect($clicked)->toBeTrue();
}
