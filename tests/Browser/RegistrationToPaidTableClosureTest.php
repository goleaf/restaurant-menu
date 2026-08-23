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

test('owner can register and close a fully paid table through the browser', function () {
    $this->seed(SystemPermissionsSeeder::class);

    $page = visit(route('register', absolute: false));

    $page
        ->assertSee(__('ui.auth.register.create_an_account'))
        ->fill('name', 'Browser E2E Owner')
        ->fill('email', 'browser-e2e-owner@example.test')
        ->fill('password', 'password')
        ->fill('password_confirmation', 'password')
        ->click('@register-user-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->assertNoJavaScriptErrors();

    expect(User::query()->where('email', 'browser-e2e-owner@example.test')->count())->toBe(1);

    completeBrowserRestaurantOnboarding($page);

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

function completeBrowserRestaurantOnboarding(PendingAwaitablePage $page): void
{
    $page
        ->navigate(route('onboarding.restaurant', absolute: false));
    $page->assertSee(__('ui.onboarding.restaurant_setup.nastroit_restoran'));
    $page->fill('input[wire\\:model="organizationName"]', 'Browser E2E Food Group');
    clickBrowserElement($page, 'form[wire\\:submit="createOrganization"] button[type="submit"]');
    $page->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_restorana'));

    $page->fill('input[wire\\:model="brandName"]', 'Browser E2E Bistro');
    clickBrowserElement($page, 'form[wire\\:submit="createBrand"] button[type="submit"]');
    $page->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_tocki'));

    $page
        ->fill('input[wire\\:model="branchName"]', 'Browser E2E Bistro Old Town')
        ->fill('input[wire\\:model="branchAddress"]', 'Pilies 1')
        ->fill('input[wire\\:model="branchCity"]', 'Vilnius')
        ->fill('input[wire\\:model="branchCountry"]', 'Lithuania')
        ->fill('input[wire\\:model="branchTimezone"]', 'Europe/Vilnius')
        ->select('select[wire\\:model="branchCurrency"]', 'EUR');
    clickBrowserElement($page, 'form[wire\\:submit="createBranch"] button[type="submit"]');
    $page->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_zony'));

    $page->fill('input[wire\\:model="areaName"]', 'Browser E2E Hall');
    clickBrowserElement($page, 'form[wire\\:submit="createArea"] button[type="submit"]');
    $page->assertSee(__('ui.onboarding.restaurant_setup.skolko_stolov'));

    $page
        ->fill('input[wire\\:model="tableCount"]', '1')
        ->fill('input[wire\\:model="tablePrefix"]', 'Browser E2E Table')
        ->fill('input[wire\\:model="tableCapacity"]', '4');
    clickBrowserElement($page, 'form[wire\\:submit="createServicePoints"] button[type="submit"]');
    $page->assertSee(__('ui.onboarding.restaurant_setup.sgenerirovat_qr'));

    clickBrowserElement($page, 'button[wire\\:click="generateQrCodes"]');
    $page->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_meniu'));

    $page
        ->fill('input[wire\\:model="menuName"]', 'Browser E2E Menu')
        ->fill('input[wire\\:model="categoryName"]', 'Browser E2E Main')
        ->fill('input[wire\\:model="itemName"]', 'Browser E2E Pasta')
        ->fill('input[wire\\:model="itemPrice"]', '8.50');
    clickBrowserElement($page, 'form[wire\\:submit="createStarterMenu"] button[type="submit"]');
    $page
        ->assertSee(__('ui.onboarding.restaurant_setup.restoran_gotov_k_proverke'))
        ->assertNoJavaScriptErrors();
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
