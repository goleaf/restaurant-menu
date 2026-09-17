<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Tests\Support\IsolatedBrowserIdentity;

test('pending restaurant settings keep their address and resource through Back and Forward without replay', function (bool $fallback): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $first = Branch::factory()->withDefaultSettings()->create(['name' => 'History restaurant A', 'public_name' => 'Original A']);
    $second = Branch::factory()->forBrand($first->brand)->withDefaultSettings()->create(['name' => 'History restaurant B', 'public_name' => 'Original B']);
    $third = Branch::factory()->forBrand($first->brand)->withDefaultSettings()->create(['name' => 'History restaurant C', 'public_name' => 'Original C']);
    $user = User::factory()->create(['password' => 'password']);
    OrganizationUser::factory()->forOrganization($first->organization)->forUser($user)->forSystemRole(SystemRole::Owner)->active()->create();
    $paths = array_map(fn (Branch $branch): string => route('organizations.brands.branches.settings.index', [$branch->organization_id, $branch->brand_id, $branch->id], false), [$first, $second, $third]);
    $page = visit(route('login', absolute: false));
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')->assertPathIs(route('dashboard', absolute: false));
    $capabilities = $page->script('({ userAgent: navigator.userAgent, navigation: typeof window.navigation, index: window.navigation?.currentEntry?.index ?? null, protocol: location.protocol })');
    file_put_contents(storage_path('framework/workspace-history-capabilities-'.($fallback ? 'fallback' : 'native').'.json'), json_encode($capabilities, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    if ($fallback) {
        $page->script("Object.defineProperty(window, 'navigation', { configurable: true, value: undefined })");
    }
    $page->script(<<<'JS'
        window.workspaceHistoryEvents = 0;
        window.addEventListener('popstate', () => { window.workspaceHistoryEvents++; }, { capture: true });
        JS);
    foreach ($paths as $index => $path) {
        $page->script('Livewire.navigate('.json_encode($path, JSON_THROW_ON_ERROR).')');
        $page->assertPathIs($path)->assertVisible('[data-page="branch-settings"]');
        if ($index === 0) {
            $page->script('location.hash = "main-content"');
            $page->assertScript('location.hash', '#main-content');
        }
    }
    $page->script('history.back()');
    $page->assertPathIs($paths[1])->assertValue('input[wire\\:model="form.publicName"]', 'Original B');
    $page->script(<<<'JS'
        window.workspaceHistoryRequests = 0;
        window.workspaceHistoryResponseReady = false;
        window.workspaceHistoryOriginalFetch = window.fetch;
        window.fetch = async (...args) => {
            const save = typeof args[1]?.body === 'string' && JSON.parse(args[1].body).components?.some(component =>
                JSON.parse(component.snapshot).memo.name === 'organizations.brands.branches.settings'
                && component.calls.some(call => call.method === 'save'));
            if (save) window.workspaceHistoryRequests++;
            const response = await window.workspaceHistoryOriginalFetch(...args);
            if (save) {
                window.workspaceHistoryResponseReady = true;
                await new Promise(resolve => { window.releaseWorkspaceHistoryResponse = resolve; });
            }
            return response;
        };
        JS);
    $page->fill('input[wire\\:model="form.publicName"]', 'Saved B')->click('form[wire\\:submit="save"] button[type="submit"]')
        ->assertScript('window.workspaceHistoryResponseReady === true');
    try {
        foreach ([-2, 1] as $distance) {
            $page->script('window.workspaceHistoryEvents = 0; history.go('.$distance.')');
            $page->assertScript('window.workspaceHistoryEvents > 0')
                ->assertPathIs($paths[1])
                ->assertSee($second->name)
                ->assertAttribute('[data-navigation-key="settings"]', 'aria-current', 'page')
                ->assertValue('input[wire\\:model="form.publicName"]', 'Saved B')
                ->assertScript('Alpine.$data(document.body).pending', 1);
        }
    } finally {
        $page->script('window.releaseWorkspaceHistoryResponse?.(); window.fetch = window.workspaceHistoryOriginalFetch;');
    }
    $page->assertScript('Alpine.$data(document.body).pending', 0)->assertPathIs($paths[1])
        ->assertScript('window.workspaceHistoryRequests', 1);
    expect($first->fresh()->public_name)->toBe('Original A')
        ->and($second->fresh()->public_name)->toBe('Saved B')
        ->and($third->fresh()->public_name)->toBe('Original C');
    $page->script('history.back()');
    $page->assertPathIs($paths[0])->assertValue('input[wire\\:model="form.publicName"]', 'Original A');
    $page->script('history.forward()');
    $page->assertPathIs($paths[1])->assertValue('input[wire\\:model="form.publicName"]', 'Saved B')
        ->assertScript('window.workspaceHistoryRequests', 1)->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['native index' => false, 'fallback index' => true]);
