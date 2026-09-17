<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;

test('removed tooltip observers stop after repeated Livewire navigation while notification polling stays singular', function (): void {
    $this->withVite();
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create(['email' => 'tooltip-lifecycle@example.test', 'password' => 'password']);
    $page = visit(route('login', absolute: false));
    $page->page()->context()->addInitScript(fluxTooltipObserverProbe());
    $page->resize(390, 844)->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->assertVisible('[data-workspace-entry]')
        ->assertScript('document.readyState', 'complete');
    $page->script(<<<'JS'
        (() => {
            const original = window.fetch;
            window.notificationPolling = { document: 'same-document', requests: [] };
            window.fetch = async (...args) => {
                let observation;
                if (String(args[0]).includes('/livewire') && typeof args[1]?.body === 'string') {
                    const payload = JSON.parse(args[1].body);
                    const calls = (payload.components ?? []).flatMap(component =>
                        JSON.parse(component.snapshot).memo.name === 'notifications.unread-count'
                            ? component.calls.filter(call => call.method === 'refreshUnreadCount') : []);
                    if (calls.length) {
                        observation = { calls: calls.length, status: null, renders: null };
                        window.notificationPolling.requests.push(observation);
                    }
                }
                const response = await original(...args);
                if (observation) {
                    observation.status = response.status;
                    const payload = await response.clone().json();
                    observation.renders = payload.components.some(component => Boolean(component.effects.html));
                }
                return response;
            };
        })()
        JS);
    $page->click('[data-flux-sidebar-toggle]')
        ->assertAttributeMissing('[data-flux-sidebar]', 'data-flux-sidebar-collapsed-mobile');
    assertFluxTooltipPollingWindow($page);

    foreach (range(1, 10) as $visit) {
        $page->hover('[data-navigation-search-trigger]')->hover('[data-flux-sidebar-toggle]')
            ->assertScript('document.querySelector("[data-flux-sidebar-toggle]").closest("ui-tooltip").querySelector("[popover]").matches(":popover-open")', true);
        $destination = route($visit % 2 === 0 ? 'dashboard' : 'organizations.index', absolute: false);
        $page->script('Livewire.navigate('.json_encode($destination, JSON_THROW_ON_ERROR).');');
        $page->assertPathIs($destination)
            ->assertScript('window.notificationPolling.document', 'same-document')
            ->assertScript('document.querySelectorAll("[data-component=notifications-unread-count]").length', 1);
    }
    $page->script('history.back();');
    $page->assertPathIs(route('organizations.index', absolute: false));
    $page->script('history.forward();');
    $page->assertPathIs(route('dashboard', absolute: false));
    $page->click('[data-flux-sidebar-toggle]')
        ->assertAttributeMissing('[data-flux-sidebar]', 'data-flux-sidebar-collapsed-mobile');
    assertFluxTooltipPollingWindow($page);
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    $page->assertScript('window.fluxTooltipObserverState().created > 0', true)
        ->assertScript('window.fluxTooltipObserverState().callbacks > 0', true)
        ->assertScript('window.fluxTooltipObserverState().detachedObservers', 0)
        ->assertScript('window.fluxTooltipObserverState().callbacksAfterRemoval', 0);
});

function assertFluxTooltipPollingWindow(PendingAwaitablePage $page): void
{
    $page->script('window.notificationPolling.requests = [];');
    $page->assertScript('window.notificationPolling.requests.some(request => request.status === 200 && request.renders !== null)');
    $page->script('window.notificationPolling.requests = [];');
    $page->wait(6);
    $page->assertScript('window.notificationPolling.requests.length', 1)
        ->assertScript('window.notificationPolling.requests.every(request => request.calls === 1 && request.status === 200 && request.renders === false)')
        ->assertMissing('[data-notification-item]');
}

function fluxTooltipObserverProbe(): string
{
    return <<<'JS'
        (() => {
            const NativeObserver = window.ResizeObserver;
            const records = [];
            const targetIds = new WeakMap();
            let nextTargetId = 0;
            window.ResizeObserver = class extends NativeObserver {
                constructor(callback) {
                    const state = { tooltip: false, targets: new Map(), callbacks: 0, callbacksAfterRemoval: 0 };
                    super(function (entries, observer) {
                        if (state.tooltip) {
                            state.callbacks++;
                            if (entries.some(entry => !entry.target.isConnected)) state.callbacksAfterRemoval++;
                        }
                        return callback.call(this, entries, observer);
                    });
                    this.record = state;
                    records.push(state);
                }
                observe(target, options) {
                    const result = super.observe(target, options);
                    if (target.closest('ui-tooltip')) {
                        this.record.tooltip = true;
                        if (!targetIds.has(target)) targetIds.set(target, ++nextTargetId);
                        this.record.targets.set(targetIds.get(target), new WeakRef(target));
                    }
                    return result;
                }
                unobserve(target) {
                    const result = super.unobserve(target);
                    this.record.targets.delete(targetIds.get(target));
                    return result;
                }
                disconnect() {
                    const result = super.disconnect();
                    this.record.targets.clear();
                    return result;
                }
            };
            window.fluxTooltipObserverState = () => {
                const tooltips = records.filter(record => record.tooltip);
                return {
                    created: tooltips.length,
                    callbacks: tooltips.reduce((total, record) => total + record.callbacks, 0),
                    callbacksAfterRemoval: tooltips.reduce((total, record) => total + record.callbacksAfterRemoval, 0),
                    detachedObservers: tooltips.filter(record => [...record.targets.values()].some(reference => !reference.deref()?.isConnected)).length,
                };
            };
        })();
        JS;
}
