<?php

declare(strict_types=1);

use App\Enums\DraftOrderStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\ManualPayment;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\QrCode;
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

    expect($order->status)->toBe(OrderStatus::ConfirmedByWaiter)
        ->and($order->total_price_cents)->toBe(1250)
        ->and($order->items()->sole()->variant_name)->toBe('Large portion');

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
        ->assertSee('12.50 EUR')
        ->select('select[wire\\:model="paymentMethod"]', ManualPaymentMethod::CardTerminal->value)
        ->fill('paymentNote', 'Browser E2E card payment');
    clickBrowserButtonContaining($page, __('payments.pay_whole_table').' · 12.50 EUR');
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
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Paid);

    clickBrowserElement($page, '#close-table button');
    clickBrowserElement($page, 'button[wire\\:click="closeTableSession"]');
    $page
        ->assertSee(__('payments.messages.session_closed'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed)
        ->and($tableSession->fresh()->ended_at)->not->toBeNull()
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free);
});

function completeBrowserRestaurantOnboarding(PendingAwaitablePage $page, User $registeredOwner): void
{
    assertBrowserOnboardingLocaleLayout($page, $registeredOwner, 'lt', 'en');
    assertBrowserOnboardingLocaleLayout($page, $registeredOwner, 'ru', 'lt');
    assertBrowserOnboardingLocaleLayout($page, $registeredOwner, 'en', 'ru');

    $page
        ->resize(390, 844)
        ->assertSee(__('ui.onboarding.restaurant_setup.nastroit_restoran'))
        ->assertVisible('progress[aria-label]')
        ->assertAttribute('input[name="organization_name"]', 'type', 'text')
        ->assertAttribute('input[name="organization_name"]', 'autocomplete', 'organization');
    assertBrowserHasNoHorizontalOverflow($page, 320, 720);
    assertBrowserHasNoHorizontalOverflow($page, 390, 844);
    assertBrowserHasNoHorizontalOverflow($page, 768, 900);
    assertBrowserHasNoHorizontalOverflow($page, 1024, 900);
    assertBrowserHasNoHorizontalOverflow($page, 1440, 1000);
    assertBrowserOnboardingTextZoomReflow($page);
    $page
        ->resize(1440, 1000)
        ->assertVisible('nav[aria-label]');
    assertBrowserDarkThemeLayout($page);
    assertBrowserKeyboardFocusIsVisible($page);

    $page->fill('input[name="organization_name"]', 'Browser E2E Food Group');
    clickBrowserElement($page, 'form[wire\\:submit="createOrganization"] button[type="submit"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_restorana'))
        ->navigate(route('onboarding.restaurant', absolute: false))
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_restorana'))
        ->assertVisible('[data-onboarding-mobile-summary]');

    clickBrowserElement($page, 'button[wire\\:click="goToStep(1)"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_kompanii'))
        ->assertValue('input[name="organization_name"]', 'Browser E2E Food Group');
    clickBrowserElement($page, 'form[wire\\:submit="createOrganization"] button[type="submit"]');
    $page->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_restorana'));

    $page->fill('input[name="brand_name"]', 'Browser E2E Bistro');
    clickBrowserElement($page, 'form[wire\\:submit="createBrand"] button[type="submit"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_filiala'))
        ->assertPresent('#restaurant-country-options')
        ->assertPresent('#restaurant-timezone-options')
        ->assertAttribute('input[name="branch_country_code"]', 'list', 'restaurant-country-options')
        ->assertAttribute('input[name="branch_country_code"]', 'autocomplete', 'country')
        ->assertAttribute('input[name="branch_timezone"]', 'list', 'restaurant-timezone-options');

    $page
        ->fill('input[name="branch_name"]', 'Browser E2E Bistro Old Town')
        ->fill('input[name="branch_address"]', 'Pilies 1')
        ->fill('input[name="branch_city"]', 'Vilnius')
        ->fill('input[name="branch_country_code"]', 'ZZ')
        ->fill('input[name="branch_timezone"]', 'Europe/Vilnius')
        ->select('select[name="branch_currency"]', 'EUR');
    clickBrowserElement($page, 'form[wire\\:submit="createBranch"] button[type="submit"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.validation_heading'))
        ->assertAttribute('input[name="branch_country_code"]', 'aria-invalid', 'true')
        ->assertAttribute('input[name="branch_country_code"]', 'aria-describedby', 'branch-country-code-help branch-country-code-error')
        ->assertPresent('#branch-country-code-help')
        ->assertPresent('#branch-country-code-error[role="alert"]')
        ->assertScript('document.activeElement?.name', 'branch_country_code');

    $page->fill('input[name="branch_country_code"]', 'LT');
    clickBrowserElement($page, 'form[wire\\:submit="createBranch"] button[type="submit"]');
    $page->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_zony'));

    $page
        ->assertPresent('select[name="area_type"] option[value="bar_area"]')
        ->assertPresent('select[name="area_icon"] option[value="sparkles"]')
        ->fill('input[name="area_name"]', 'Browser E2E Hall');
    clickBrowserElement($page, 'form[wire\\:submit="createArea"] button[type="submit"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.skolko_stolov'))
        ->assertAttribute('input[name="table_count"]', 'step', '1')
        ->assertAttribute('input[name="table_count"]', 'inputmode', 'numeric')
        ->assertAttribute('input[name="table_capacity"]', 'max', '50');

    $page
        ->fill('input[name="table_count"]', '1')
        ->fill('input[name="table_prefix"]', 'Browser E2E Table')
        ->fill('input[name="table_capacity"]', '4');
    clickBrowserElement($page, 'form[wire\\:submit="createServicePoints"] button[type="submit"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.sgenerirovat_qr'))
        ->navigate(route('onboarding.restaurant', absolute: false))
        ->assertSee(__('ui.onboarding.restaurant_setup.sgenerirovat_qr'));

    clickBrowserElement($page, 'button[wire\\:click="generateQrCodes"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_meniu'))
        ->assertAttribute('input[name="item_price"]', 'type', 'number')
        ->assertAttribute('input[name="item_price"]', 'step', '0.01')
        ->assertAttribute('input[name="item_price"]', 'inputmode', 'decimal');

    $page
        ->fill('input[name="menu_name"]', 'Browser E2E Menu')
        ->fill('input[name="category_name"]', 'Browser E2E Main')
        ->fill('input[name="item_name"]', 'Browser E2E Pasta')
        ->fill('input[name="item_price"]', '8.50');
    clickBrowserElement($page, 'form[wire\\:submit="createStarterMenu"] button[type="submit"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.restoran_gotov_k_proverke'))
        ->assertSee(__('ui.onboarding.restaurant_setup.primary_next_step'))
        ->assertVisible('a[target="_blank"][rel="noopener"]')
        ->assertNoJavaScriptErrors();
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
    string $requestLocale,
): void {
    $page->navigate(route('profile.edit', absolute: false));
    $page->select('select[wire\\:model="locale"]', $locale);
    clickBrowserElement($page, 'form[wire\\:submit="updateProfileInformation"] button[type="submit"]');
    $page->assertSee(__('ui.livewire.settings.profile.profile_updated', [], $requestLocale));

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

    $page->script("window.localStorage.setItem('flux.appearance', 'dark')");
    $page->resize(390, 844);
    $page->navigate(route('onboarding.restaurant', absolute: false));

    $darkCanvas = $page->script('getComputedStyle(document.body).backgroundColor');

    expect($darkCanvas)->not->toBe($lightCanvas);

    assertBrowserHasNoHorizontalOverflow($page, 390, 844);

    $page->script("window.localStorage.setItem('flux.appearance', 'light')");
    $page->navigate(route('onboarding.restaurant', absolute: false));
}

function assertBrowserKeyboardFocusIsVisible(PendingAwaitablePage $page): void
{
    $page->keys('[data-page="restaurant-onboarding"]', 'Tab');

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
