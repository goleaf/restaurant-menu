<?php

declare(strict_types=1);

use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderStatusLog;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;
use Tests\Support\PreparationWorkflowFixture;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
});

test('mixed waiter order flows through one preparation workspace with isolated family access and partial serving', function (): void {
    $fixture = PreparationWorkflowFixture::create(confirm: false);
    $url = route('restaurant.preparation.dashboard', ['branch' => $fixture['branch']->id], false);
    $tableUrl = route('restaurant.waiter.tables.show', ['tableSession' => $fixture['session']], false);
    $waiter = preparationBrowserLogin($fixture['actors']['waiter']->email);
    $waiter->navigate($tableUrl)->assertSee('Soup')->assertSee('Coffee');
    preparationBrowserClick($waiter, 'button[wire\\:click="confirmDraft"]');
    $order = Order::query()->where('draft_order_id', $fixture['draft']->id)->sole();
    expect($order->status)->toBe(OrderStatus::SentToKitchenBar);
    $items = KitchenTicketItem::query()->whereHas('kitchenTicket', fn ($query) => $query->where('order_id', $order->id))->orderBy('id')->get();
    $soup = $items->first(fn (KitchenTicketItem $item): bool => str_starts_with($item->item_name, 'Soup'));
    $pasta = $items->first(fn (KitchenTicketItem $item): bool => str_starts_with($item->item_name, 'Pasta'));
    $coffee = $items->first(fn (KitchenTicketItem $item): bool => str_starts_with($item->item_name, 'Coffee'));
    expect($items)->toHaveCount(3);

    $cook = preparationBrowserLogin($fixture['actors']['cook']->email);
    $cook->navigate($url)->assertPresent('[data-preparation-queue]')->assertSee('Soup')->assertSee('Pasta')->assertDontSee('Coffee')
        ->assertSee('Large portion')->assertSee('Extra herbs')->assertSee('Keep the guest instruction exactly')->assertSee(__('menu.allergens.options.milk'));
    $bar = preparationBrowserLogin($fixture['actors']['bar']->email);
    $bar->navigate($url)->assertSee('Coffee')->assertDontSee('Soup')->assertDontSee('Pasta');
    $chef = preparationBrowserLogin($fixture['actors']['chef']->email);
    $chef->navigate($url.'&department=all')->assertSee('Soup')->assertSee('Pasta')->assertSee('Coffee');

    preparationBrowserTransition($cook, $soup->id, 'accepted');
    preparationBrowserTransition($cook, $soup->id, 'in_progress');
    preparationBrowserTransition($cook, $pasta->id, 'accepted');
    preparationBrowserTransition($cook, $pasta->id, 'in_progress');
    preparationBrowserTransition($bar, $coffee->id, 'accepted');
    preparationBrowserTransition($bar, $coffee->id, 'in_progress');
    preparationBrowserTransition($cook, $soup->id, 'ready');
    expect($soup->fresh()->served_at)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::InProgress)
        ->and($fixture['actors']['waiter']->notifications()->where('type', 'kitchen_item_ready')->count())->toBe(1);
    preparationBrowserClick($cook, '[data-preparation-view="ready"]');
    $cook->assertPresent('[data-preparation-item="'.$soup->id.'"]')->assertMissing('[data-preparation-item="'.$pasta->id.'"]')
        ->assertSee(__('statuses.kitchen_ticket_item.in_progress'))->screenshot(true, 'preparation-partial-ready');
    preparationBrowserClick($cook, '[data-preparation-print]');
    $cook->assertSee('Soup')->assertSee('Pasta')->assertSee('Large portion')->assertSee('Extra herbs')
        ->assertSee('Keep the guest instruction exactly')->assertSee(__('menu.allergens.options.milk'))
        ->assertMissing('[wire\\:poll]')->screenshot(true, 'preparation-full-ticket-print');
    expect($items->map(fn (KitchenTicketItem $item): string => $item->fresh()->status->value)->all())->toBe(['ready', 'in_progress', 'in_progress']);

    $waiter->navigate($tableUrl)->assertSee(__('ui.waiter.dashboard.mark_served'));
    preparationBrowserClick($waiter, 'button[wire\\:click="markTicketItemServed('.$soup->id.')"]');
    expect($soup->fresh()->served_at)->not->toBeNull()
        ->and($pasta->fresh()->status)->toBe(KitchenTicketItemStatus::InProgress)
        ->and($coffee->fresh()->status)->toBe(KitchenTicketItemStatus::InProgress)
        ->and($order->fresh()->status)->toBe(OrderStatus::InProgress);
    $chef->navigate($url.'&department=all&filter=active')->assertPresent('[data-preparation-item="'.$pasta->id.'"]')
        ->assertPresent('[data-preparation-item="'.$coffee->id.'"]')->assertMissing('[data-preparation-item="'.$soup->id.'"]');
    preparationBrowserClick($chef, '[data-preparation-view="history"]');
    $chef->assertPresent('[data-preparation-item="'.$soup->id.'"]')->assertSee(__('ui.departments.dashboard.completed'));
    foreach ([$cook, $bar, $chef, $waiter] as $page) {
        $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    }
});

test('preparation reconnect refreshes before actions and independent tabs cannot replay an obsolete transition', function (): void {
    $fixture = PreparationWorkflowFixture::create();
    $item = $fixture['items']->first();
    $url = route('restaurant.preparation.dashboard', ['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id], false);
    $first = preparationBrowserLogin($fixture['actors']['chef']->email);
    $first->navigate($url)->assertPresent('[data-preparation-item="'.$item->id.'"]');
    $second = new AwaitableWebpage($first->page()->context()->newPage(), $first->url());
    $second->navigate($url)->assertPresent('[data-preparation-item="'.$item->id.'"]');
    preparationBrowserTransition($first, $item->id, 'accepted');
    preparationBrowserTransition($first, $item->id, 'in_progress');
    $staleArguments = json_encode([$item->id, 'accepted', 'new', (string) $item->getRawOriginal('updated_at'), $item->kitchen_ticket_id], JSON_THROW_ON_ERROR);
    $second->script("Livewire.find(document.querySelector('[data-preparation-workspace]').getAttribute('wire:id')).setItemStatus(...{$staleArguments})");
    $second->assertSee(__('preparation.errors.stale_item'));
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::InProgress)
        ->and(OrderStatusLog::query()->where('metadata->kitchen_ticket_item_id', $item->id)->where('event', 'ticket_item_status_changed')->count())->toBe(2);

    preparationBrowserOffline($first, true);
    $first->assertScript('navigator.onLine', false)->assertDisabled('[data-preparation-item="'.$item->id.'"] [data-preparation-transition="ready"]');
    preparationBrowserOffline($first, false);
    $first->assertScript('navigator.onLine', true)->assertEnabled('[data-preparation-item="'.$item->id.'"] [data-preparation-transition="ready"]');
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::InProgress);
    $first->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    $second->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('a lost ready response and a session ended in another tab cannot repeat preparation work', function (): void {
    $fixture = PreparationWorkflowFixture::create();
    $item = $fixture['items']->first();
    $other = $fixture['items']->skip(1)->first();
    $url = route('restaurant.preparation.dashboard', ['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id], false);
    $page = preparationBrowserLogin($fixture['actors']['chef']->email);
    $page->navigate($url);
    preparationBrowserTransition($page, $item->id, 'accepted');
    preparationBrowserTransition($page, $item->id, 'in_progress');
    $page->script(<<<'JS'
        window.preparationResponseLost = false;
        const originalFetch = window.fetch.bind(window);
        window.fetch = async (...arguments_) => {
            const response = await originalFetch(...arguments_);
            const body = arguments_[1]?.body;
            if (typeof body === 'string' && body.includes('setItemStatus') && body.includes('ready')) {
                window.preparationResponseLost = true;
                return new Promise(() => {});
            }
            return response;
        };
        JS);
    preparationBrowserTransition($page, $item->id, 'ready');
    $page->assertScript('window.preparationResponseLost', true);
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and($item->fresh()->served_at)->toBeNull();
    $page->navigate($url.'&filter=ready')->assertPresent('[data-preparation-item="'.$item->id.'"]');
    expect(OrderStatusLog::query()->where('metadata->kitchen_ticket_item_id', $item->id)->where('new_status', 'ready')->count())->toBe(1)
        ->and($fixture['actors']['waiter']->notifications()->where('type', 'kitchen_item_ready')->count())->toBe(1);
    $page->navigate($url)->assertPresent('[data-preparation-item="'.$other->id.'"]');
    $second = new AwaitableWebpage($page->page()->context()->newPage(), $page->url());
    $second->navigate($url)->click('@sidebar-menu-button')->click('@logout-button')->assertMissing('[data-preparation-workspace]');
    preparationBrowserTransition($page, $other->id, 'accepted');
    $page->assertPathIs(route('login', absolute: false))->assertPresent('input[type="password"]');
    expect($other->fresh()->status)->toBe(KitchenTicketItemStatus::New)
        ->and(OrderStatusLog::query()->where('metadata->kitchen_ticket_item_id', $other->id)->where('event', 'ticket_item_status_changed')->count())->toBe(0);
    $page->fill('email', $fixture['actors']['chef']->email)->fill('password', 'password')->click('@login-button')
        ->assertMissing('input[type="password"]')->navigate($url)->assertPresent('[data-preparation-item="'.$other->id.'"]');
    expect($other->fresh()->status)->toBe(KitchenTicketItemStatus::New)
        ->and($item->fresh()->served_at)->toBeNull();
    $page->assertNoJavaScriptErrors();
});

test('preparation selection review fits a narrow screen and can close while offline', function (): void {
    $fixture = PreparationWorkflowFixture::create();
    $item = $fixture['items']->first();
    $page = preparationBrowserLogin($fixture['actors']['chef']->email);
    $page->navigate(route('restaurant.preparation.dashboard', ['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id], false));
    $selector = '[data-preparation-item="'.$item->id.'"] [data-flux-checkbox]';
    $page->assertAttribute($selector, 'role', 'checkbox')->click($selector)
        ->assertScript('Livewire.find(document.querySelector("[data-preparation-workspace]").getAttribute("wire:id")).selection.itemIds', [(string) $item->id])
        ->assertNoJavaScriptErrors();
    preparationBrowserClick($page, '[data-preparation-selection-review]');
    $page->assertVisible('dialog[data-modal="preparation-review"][open]')->assertSee('Keep the guest instruction exactly')->assertSee('Extra herbs');
    foreach ([320, 390] as $width) {
        $page->resize($width, 844)->assertScript('(() => { const box = document.querySelector("dialog[data-modal=preparation-review]").getBoundingClientRect(); return box.left >= 0 && box.right <= innerWidth; })()', true)
            ->screenshot(true, 'preparation-selection-'.$width);
    }
    preparationBrowserOffline($page, true);
    $page->assertDisabled('[data-preparation-selection-apply]')->click('dialog[data-modal="preparation-review"] button[autofocus]')
        ->assertMissing('dialog[data-modal="preparation-review"][open]');
    preparationBrowserOffline($page, false);
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::New);
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('preparation renders operational controls across translated narrow wide and contrast modes', function (string $locale): void {
    $fixture = PreparationWorkflowFixture::create();
    $actor = $fixture['actors']['chef'];
    $actor->update(['locale' => $locale]);
    app()->setLocale($locale);
    $page = preparationBrowserLogin($actor->email);
    $url = route('restaurant.preparation.dashboard', ['branch' => $fixture['branch']->id, 'department' => 'all', 'lang' => $locale], false);
    $page->navigate($url)->assertSee(__('preparation.title'))->assertSee('Keep the guest instruction exactly');
    foreach ([320, 390, 768, 1024, 1440] as $width) {
        $page->resize($width, 1000);
        expect($page->script(<<<'JS'
            Array.from(document.querySelectorAll('.prep-workspace, .prep-context, .prep-counts, .prep-counts p, .prep-views, .prep-ticket, .prep-selection')).map(el => ({selector: el.className || el.tagName, left: el.getBoundingClientRect().left, right: el.getBoundingClientRect().right, width: innerWidth})).filter(box => box.left < -1 || box.right > box.width + 1)
            JS))->toBe([]);
        $page->assertScript('document.documentElement.scrollWidth <= innerWidth + 1', true)
            ->assertScript('Array.from(document.querySelectorAll("button[data-preparation-transition]")).filter(el => el.checkVisibility()).every(el => el.getBoundingClientRect().height >= 64)', true)
            ->screenshot(true, 'preparation-'.$locale.'-'.$width);
    }
    $page->script('document.documentElement.classList.add("dark")');
    $page->resize(390, 844)->assertScript('document.documentElement.scrollWidth <= innerWidth + 1', true)->screenshot(true, 'preparation-'.$locale.'-dark');
    $page->script('document.documentElement.classList.remove("dark"); document.documentElement.style.fontSize = "200%"');
    $page->assertScript('document.documentElement.scrollWidth <= innerWidth + 1', true);
    $page->script('document.documentElement.style.fontSize = ""');
    $guid = (new ReflectionProperty($page->page(), 'guid'))->getValue($page->page());
    foreach (Client::instance()->execute($guid, 'emulateMedia', ['forcedColors' => 'active', 'reducedMotion' => 'reduce']) as $message) {
    }
    $page->assertScript('matchMedia("(forced-colors: active)").matches && matchMedia("(prefers-reduced-motion: reduce)").matches', true)
        ->assertScript('document.documentElement.scrollWidth <= innerWidth + 1', true)->screenshot(true, 'preparation-'.$locale.'-forced-colors');
    $page->keys('[data-preparation-refresh]', 'Tab')->assertScript('document.activeElement !== document.body', true)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['en', 'lt', 'ru']);

function preparationBrowserLogin(string $email): PendingAwaitablePage
{
    $page = visit(route('login', absolute: false));
    $page->fill('email', $email)->fill('password', 'password')->click('@login-button')->assertMissing('input[type="password"]');

    return $page;
}

function preparationBrowserClick(PendingAwaitablePage|AwaitableWebpage $page, string $selector): void
{
    $page->assertVisible($selector)->assertEnabled($selector)->click($selector)->wait(0.15);
}

function preparationBrowserTransition(PendingAwaitablePage|AwaitableWebpage $page, int $itemId, string $status): void
{
    preparationBrowserClick($page, '[data-preparation-item="'.$itemId.'"] [data-preparation-transition="'.$status.'"]');
}

function preparationBrowserOffline(PendingAwaitablePage|AwaitableWebpage $page, bool $offline): void
{
    $context = $page->page()->context();
    $guid = (new ReflectionProperty($context, 'guid'))->getValue($context);
    foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
    }
}
