<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;

test('a response received before disconnection cannot enable offline controls when it renders after cached history', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $branch = Branch::factory()->withDefaultSettings()->create();
    $owner = User::factory()->create(['password' => 'password']);
    OrganizationUser::factory()->forOrganization($branch->organization)->forUser($owner)->forSystemRole(SystemRole::Owner)->active()->create();
    $center = route('restaurants.index', absolute: false);
    $staff = route('organizations.brands.branches.staff.index', [$branch->organization_id, $branch->brand_id, $branch->id], false);
    $page = visit(route('login', absolute: false));
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')->assertPathIs(route('restaurant.dashboard', absolute: false))->assertQueryStringHas('branch', (string) $branch->id);
    $page->script('Livewire.navigate('.json_encode($center, JSON_THROW_ON_ERROR).')');
    $page->assertPathIs($center)->assertVisible('[data-page="restaurant-center"]');
    $page->script('Livewire.navigate('.json_encode($staff, JSON_THROW_ON_ERROR).')');
    $page->assertPathIs($staff)->assertPresent('[data-staff-workspace]');

    $context = $page->page()->context();
    $guid = (new ReflectionProperty($context, 'guid'))->getValue($context);
    assert(is_string($guid));
    $setOffline = function (bool $offline) use ($guid): void {
        foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
            // Consume the protocol response before checking the actual document.
        }
    };
    $page->script(<<<'JS'
        (() => {
            const state = window.offlineLifecycle = { events: [], ready: false, rendered: false, held: false };
            const capture = phase => state.events.push({ phase, online: navigator.onLine, disabled: document.querySelector('[data-workspace-restaurant] button[type="submit"]')?.disabled ?? null });
            const offline = () => capture('offline-event');
            window.addEventListener('offline', offline);
            const observer = new MutationObserver(records => {
                if (records.some(record => record.target.matches('[data-workspace-restaurant] button[type="submit"]'))) capture('disabled-mutation');
            });
            observer.observe(document, { subtree: true, attributes: true, attributeFilter: ['disabled'] });
            const unsubscribe = Livewire.interceptMessage(({ message, onSuccess }) => {
                if (message.component.name !== 'workspace.restaurant-switcher' || message.component.snapshot.data.branchId !== null
                    || ![...message.actions].some(action => action.name === 'remember')) return;
                onSuccess(({ onRender }) => onRender(() => {
                    if (!state.ready) return;
                    capture('remember-render');
                    state.rendered = true;
                }));
            });
            const original = window.fetch;
            window.fetch = async (...args) => {
                const remember = typeof args[1]?.body === 'string' && JSON.parse(args[1].body).components?.some(component => {
                    const snapshot = JSON.parse(component.snapshot);
                    return snapshot.memo.name === 'workspace.restaurant-switcher' && snapshot.data.branchId === null
                        && component.calls.some(call => call.method === 'remember');
                });
                const hold = remember && !state.held;
                if (hold) state.held = true;
                const response = await original(...args);
                if (hold) {
                    state.ready = true;
                    capture('held-response');
                    await new Promise(resolve => { state.release = resolve; });
                    capture('released-response');
                }
                return response;
            };
            state.restore = () => {
                window.fetch = original;
                unsubscribe();
                observer.disconnect();
                window.removeEventListener('offline', offline);
            };
        })();
        JS);
    try {
        $page->script('history.back()');
        $page->assertPathIs($center)->assertScript('window.offlineLifecycle.ready', true);
        $setOffline(true);
        $page->assertScript('navigator.onLine', false)->assertDisabled('[data-workspace-restaurant] button[type="submit"]');
        $page->script('window.offlineLifecycle.release();');
        $page->assertScript('window.offlineLifecycle.rendered', true)
            ->assertScript('navigator.onLine', false)
            ->assertDisabled('[data-workspace-restaurant] button[type="submit"]');
        $setOffline(false);
        $page->assertEnabled('[data-workspace-restaurant] button[type="submit"]')->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    } finally {
        file_put_contents(storage_path('framework/offline-response-lifecycle.json'), json_encode($page->script('window.offlineLifecycle.events'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $page->script('window.offlineLifecycle.release?.(); window.offlineLifecycle.restore();');
        $setOffline(false);
    }
});
