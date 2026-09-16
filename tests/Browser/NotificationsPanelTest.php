<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use App\Notifications\WaiterCalledNotification;
use Database\Seeders\SystemPermissionsSeeder;

test('notification bell views messages without reading and keeps local close and scoped reading accessible', function (): void {
    $this->withVite();
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create(['email' => 'notification-panel@example.test', 'password' => 'password']);
    $other = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($user, ['name' => 'Notification Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Notification Brand']);
    $branch = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Vilniaus restoranas ir семейная терраса']);
    $point = ServicePoint::factory()->for($branch)->create(['name' => 'Šeimos stalas prie terasos · Семейный стол у террасы']);
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $guest = TableSessionGuest::factory()->for($session)->create(['guest_name' => 'Ana Notification']);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    $user->notify(new WaiterCalledNotification($call));
    $other->notify(new WaiterCalledNotification($call));
    $notification = $user->unreadNotifications()->firstOrFail();
    $originalStatus = $call->status;

    $page = visit(route('login', absolute: false));
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->resize(390, 844)->navigate(route('dashboard', absolute: false));
    $page->assertScript('document.querySelectorAll("[data-component=notifications-unread-count]").length', 1);
    $page->assertMissing('[data-notification-item]')->click('[data-notification-trigger]')
        ->assertVisible('dialog[data-modal="staff-notifications"]')->assertSee('Ana Notification');
    expect($notification->fresh()->read_at)->toBeNull();
    $page->assertScript('document.getElementById(document.querySelector("dialog[data-modal=staff-notifications]").getAttribute("aria-labelledby"))?.textContent.trim()', __('ui.notifications.unread_count.notifications'));
    expect($page->script('document.activeElement.getAttribute("aria-label")'))->toBe(__('notifications.panel.close'));

    foreach (['light', 'dark'] as $appearance) {
        $page->script("window.Flux.appearance = '{$appearance}'");
        foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
            $page->resize($width, $height);
            $page->assertScript('document.documentElement.scrollWidth <= innerWidth');
            $page->assertScript('document.querySelector("dialog[data-modal=staff-notifications]").scrollWidth <= document.querySelector("dialog[data-modal=staff-notifications]").clientWidth + 1');
        }
    }
    $page->resize(390, 844)->screenshot(false, 'notification-panel-390-dark');
    $page->keys('dialog[data-modal="staff-notifications"]', 'Escape')->assertMissing('dialog[data-modal="staff-notifications"][open]');
    expect($page->script('document.activeElement.hasAttribute("data-notification-trigger")'))->toBeTrue();
    $page->script(<<<'JAVASCRIPT'
        (async () => {
            const component = Livewire.find(document.querySelector('[data-component=notifications-unread-count]').getAttribute('wire:id'));
            await component.refreshUnreadCount();
        })()
    JAVASCRIPT);
    $page->assertMissing('[data-notification-item]');
    $user->notify(new WaiterCalledNotification($call));
    $page->script(<<<'JAVASCRIPT'
        (async () => {
            const component = Livewire.find(document.querySelector('[data-component=notifications-unread-count]').getAttribute('wire:id'));
            await component.refreshUnreadCount();
        })()
    JAVASCRIPT);
    $page->assertAttribute('[data-notification-trigger]', 'aria-label', __('notifications.panel.open', ['count' => 2]));
    $page->click('[data-notification-trigger]')->assertSee('Ana Notification');
    $page->script(<<<'JAVASCRIPT'
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => false });
        window.dispatchEvent(new Event('offline'));
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => true });
        window.dispatchEvent(new Event('online'));
    JAVASCRIPT);
    $page->assertScript('document.querySelector("[data-notification-loading]").getClientRects().length', 0);
    $page->assertScript('document.querySelector("[data-notification-failure]").getClientRects().length', 0);
    $page->script(<<<'JAVASCRIPT'
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => false });
        window.dispatchEvent(new Event('offline'));
    JAVASCRIPT);
    $page->assertSee(__('notifications.panel.offline_title'));
    $page->assertScript('[...document.querySelectorAll("dialog[data-modal=staff-notifications] button")].find(button => button.getAttribute("wire:click") === "markAllRead").matches(":disabled")');
    $page->keys('dialog[data-modal="staff-notifications"]', 'Escape')->assertMissing('dialog[data-modal="staff-notifications"][open]');
    expect($notification->fresh()->read_at)->toBeNull();
    $page->click('[data-notification-trigger]')->assertVisible('dialog[data-modal="staff-notifications"]')->assertSee(__('notifications.panel.offline_title'));
    $page->script(<<<'JAVASCRIPT'
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => true });
        window.dispatchEvent(new Event('online'));
    JAVASCRIPT);
    $page->assertSee(__('notifications.panel.load_failed'))->click(__('notifications.panel.retry'))->assertSee('Ana Notification');
    $page->click('button[wire\:click="markNotificationRead(\''.$notification->id.'\')"]');
    $page->assertSee(__('notifications.panel.read_status'));
    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($other->unreadNotifications()->count())->toBe(1)
        ->and($user->unreadNotifications()->count())->toBe(1)
        ->and($call->fresh()->status)->toBe($originalStatus);
    $page->click('button[wire\:click="markAllRead"]')->assertSee(__('notifications.panel.unread', ['count' => 0]));
    expect($user->unreadNotifications()->count())->toBe(0)->and($other->unreadNotifications()->count())->toBe(1);
    $page->script(<<<'JAVASCRIPT'
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => false });
        window.dispatchEvent(new Event('offline'));
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => true });
        window.dispatchEvent(new Event('online'));
    JAVASCRIPT);
    $page->assertScript('[...document.querySelectorAll("dialog[data-modal=staff-notifications] button")].find(button => button.getAttribute("wire:click") === "markAllRead").disabled');
    $page->keys('dialog[data-modal="staff-notifications"]', 'Escape');
    $page->navigate(route('organizations.index', absolute: false))->navigate(route('dashboard', absolute: false));
    $page->assertScript('document.querySelectorAll("[data-component=notifications-unread-count]").length', 1);
    foreach (['lt', 'ru'] as $locale) {
        $page->navigate(route('dashboard', ['lang' => $locale], false))->resize(390, 844);
        $page->click('[data-notification-trigger]')->assertSee('Ana Notification');
        $page->assertAttribute('dialog[data-modal="staff-notifications"] button[autofocus]', 'aria-label', __('notifications.panel.close', [], $locale));
        $page->script("document.documentElement.style.fontSize = '200%'");
        $page->assertScript('document.querySelector("dialog[data-modal=staff-notifications]").scrollWidth <= document.querySelector("dialog[data-modal=staff-notifications]").clientWidth + 1');
        $page->screenshot(false, "notification-panel-{$locale}-200-percent");
        $page->script("document.documentElement.style.fontSize = ''");
        $page->keys('dialog[data-modal="staff-notifications"]', 'Escape');
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('notification history keeps bounded pages and discards a late response after local close', function (): void {
    $this->withVite();
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create(['email' => 'notification-history@example.test', 'password' => 'password']);
    $organization = (new CreateOrganizationAction)->handle($user, ['name' => 'Notification History Group']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $point = ServicePoint::factory()->for($branch)->create();
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $guest = TableSessionGuest::factory()->for($session)->create(['guest_name' => 'History Guest']);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    foreach (range(1, 25) as $index) {
        $user->notify(new WaiterCalledNotification($call));
    }

    $page = visit(route('login', absolute: false));
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->resize(390, 844)->navigate(route('dashboard', absolute: false))->click('[data-notification-trigger]')
        ->assertSee('History Guest')->assertScript('document.querySelectorAll("[data-notification-item]").length', 20)
        ->click('[data-history-action="older"]')
        ->assertScript('document.querySelectorAll("[data-notification-item]").length', 5)
        ->assertScript('document.activeElement.hasAttribute("data-history-heading")')
        ->assertScript('document.querySelector("[data-history-action=older]").disabled');
    expect($user->unreadNotifications()->count())->toBe(25);
    $page->script(<<<'JAVASCRIPT'
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => false });
        window.dispatchEvent(new Event('offline'));
    JAVASCRIPT);
    $page->assertScript('[...document.querySelectorAll("[data-history-action]")].every(button => button.matches(":disabled"))');
    $page->script(<<<'JAVASCRIPT'
        Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => true });
        window.dispatchEvent(new Event('online'));
    JAVASCRIPT);
    $page->assertScript('document.querySelector("[data-history-action=older]").disabled')
        ->assertScript('document.querySelector("[data-history-action=newer]").disabled', false);
    $page->script(<<<'JAVASCRIPT'
        window.notificationHistoryItems = [...document.querySelectorAll('[data-notification-item]')].map(item => item.dataset.notificationItem);
    JAVASCRIPT);
    $this->travel(1)->seconds();
    $user->notify(new WaiterCalledNotification($call));
    $page->script(<<<'JAVASCRIPT'
        (async () => {
            const component = Livewire.find(document.querySelector('[data-component=notifications-unread-count]').getAttribute('wire:id'));
            await component.refreshUnreadCount();
        })()
    JAVASCRIPT);
    $page->assertSee(__('notifications.panel.unread', ['count' => 26]))
        ->assertScript('JSON.stringify([...document.querySelectorAll("[data-notification-item]")].map(item => item.dataset.notificationItem)) === JSON.stringify(window.notificationHistoryItems)');
    $page->click('[data-history-action="latest"]')->assertScript('document.querySelectorAll("[data-notification-item]").length', 20)
        ->assertScript('document.querySelector("[data-history-action=latest]").disabled');

    $page->script(<<<'JAVASCRIPT'
        window.notificationHistoryOriginalFetch = window.fetch;
        window.fetch = async (...args) => {
            const response = await window.notificationHistoryOriginalFetch(...args);
            if (typeof args[1]?.body === 'string' && args[1].body.includes('browseHistory')) {
                window.notificationHistoryResponseReady = true;
                await new Promise(resolve => { window.releaseNotificationHistoryResponse = resolve; });
            }
            return response;
        };
    JAVASCRIPT);
    $page->click('[data-history-action="older"]')->assertScript('window.notificationHistoryResponseReady === true')
        ->keys('dialog[data-modal="staff-notifications"]', 'Escape')
        ->assertMissing('dialog[data-modal="staff-notifications"][open]');
    $page->script('window.releaseNotificationHistoryResponse(); window.fetch = window.notificationHistoryOriginalFetch;');
    $page->assertScript('Livewire.find(document.querySelector("[data-component=notifications-unread-count]").getAttribute("wire:id")).panelOpen', false)
        ->assertMissing('dialog[data-modal="staff-notifications"][open]')
        ->assertScript('document.activeElement.hasAttribute("data-notification-trigger")')
        ->click('[data-notification-trigger]')->assertSee('History Guest')
        ->assertScript('document.querySelectorAll("[data-notification-item]").length', 20)
        ->assertScript('document.querySelector("[data-history-action=latest]").disabled');
    $page->script("window.Flux.appearance = 'dark'; document.documentElement.style.fontSize = '200%'; document.querySelector('[data-history-heading]').scrollIntoView({ block: 'start' })");
    $page->assertScript('document.querySelector("dialog[data-modal=staff-notifications]").scrollWidth <= document.querySelector("dialog[data-modal=staff-notifications]").clientWidth + 1')
        ->screenshot(false, 'notification-history-390-dark-200-percent')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($user->unreadNotifications()->count())->toBe(26);
});
