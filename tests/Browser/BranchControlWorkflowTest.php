<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\DemoRestaurantSeeder;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Client;

test('branch control preserves URL history local periods and ordering drafts across responsive themes', function (): void {
    $this->withVite();
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['restaurant-menu.test', '127.0.0.1', 'localhost']);
    $this->seed(DemoRestaurantSeeder::class);
    $owner = DemoAccountCatalog::forRole(SystemRole::Owner);
    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $branch = Branch::query()->where('organization_id', $organization->id)->firstOrFail();
    $otherBranch = Branch::query()->where('organization_id', $organization->id)->whereKeyNot($branch->id)->where('is_active', true)->firstOrFail();
    $branch->forceFill(['name' => 'Vilniaus senamiesčio restoranas ir šeimos terasa'])->save();
    $page = visit(route('demo-login.index', absolute: false));
    branchControlClick($page, sprintf('li[wire\\:key="demo-%s"] form[wire\\:submit] button[type="submit"]', $owner['role']->value));
    $page->assertPathIs(route('dashboard', absolute: false));
    $page->navigate(route('restaurant.dashboard', absolute: false));
    $page->assertSee(__('dashboard.control.all_branches_description'))
        ->assertPresent('[data-workspace-restaurant]');
    $page->navigate(route('restaurant.dashboard', ['branch' => $branch->id], false));
    $page->assertSee($branch->name)->assertPresent('[data-dashboard-operations]')->assertPresent('[data-dashboard-readiness]');
    branchControlClick($page, '.workspace-restaurant__trigger');
    branchControlClick($page, 'dialog[data-modal="workspace-restaurant"] [data-flux-select-button]');
    $page->fill('dialog[data-modal="workspace-restaurant"] input', 'No matching restaurant')
        ->keys('dialog[data-modal="workspace-restaurant"] input', 'ArrowDown');
    $page->assertSee(__('workspace.search_empty'))
        ->assertQueryStringHas('branch', (string) $branch->id)
        ->assertSee($branch->name);
    $page->fill('dialog[data-modal="workspace-restaurant"] input', '');
    $page->keys('dialog[data-modal="workspace-restaurant"]', 'Escape')
        ->assertMissing('dialog[data-modal="workspace-restaurant"][open]');
    $page->assertScript("document.activeElement.matches('.workspace-restaurant__trigger')", true);
    $page->select('select[name="periodDraft"]', 'custom');
    $page->fill('input[name="dateFromDraft"]', '2026-06-01')->fill('input[name="dateToDraft"]', '2026-06-07');
    branchControlClick($page, '[data-dashboard-period-form] button[type="submit"]');
    $page->assertQueryStringHas('period', 'custom:2026-06-01:2026-06-07');
    $page->select('select[name="periodDraft"]', 'yesterday');
    branchControlClick($page, '[data-dashboard-period-form] button[type="submit"]');
    $page->assertQueryStringHas('period', 'yesterday');
    $page->script('history.back()');
    $page->wait(1)->assertQueryStringHas('period', 'custom:2026-06-01:2026-06-07')->assertVisible('input[name="dateFromDraft"]');
    $page->script('history.forward()');
    $page->wait(1)->assertQueryStringHas('period', 'yesterday');

    expect($page->script("document.querySelector('input[name=\"dateFromDraft\"]').getClientRects().length"))->toBe(0);
    $availabilityPath = route('organizations.brands.branches.availability.index', ['organization' => $organization, 'brand' => $branch->brand_id, 'branch' => $branch], false);
    $otherAvailabilityPath = route('organizations.brands.branches.availability.index', ['organization' => $organization, 'brand' => $otherBranch->brand_id, 'branch' => $otherBranch], false);
    $originalReason = $branch->temporary_closed_reason;
    branchControlClick($page, '[data-dashboard-ordering] a[href*="/availability"]');
    $page->assertPathIs($availabilityPath)->assertPresent('[data-availability-panel="now"]');
    $page->assertScript("getComputedStyle(document.querySelector('.rm-availability')).display", 'grid');
    $page->assertScript("parseFloat(getComputedStyle(document.querySelector('.rm-availability')).rowGap) > 0", true);
    branchControlClick($page, 'button[wire\\:click="openPause"]');
    $page->fill('textarea[name="pause.reason"]', 'Browser unsaved reason');
    branchControlChooseBranch($page, $otherBranch);
    $page->assertVisible('dialog[data-modal="availability-unsaved"]')
        ->assertSee(__('availability.unsaved_description'))->assertPathIs($availabilityPath);
    branchControlClick($page, 'dialog[data-modal="availability-unsaved"] button[x-on\\:click="cancelNavigation"]');
    $page->assertMissing('dialog[data-modal="availability-unsaved"][open]')
        ->assertValue('textarea[name="pause.reason"]', 'Browser unsaved reason');
    $page->keys('body[class]', 'Escape')->assertMissing('dialog[data-modal="workspace-restaurant"][open]');
    branchControlClick($page, 'button[x-on\\:click="cancelDraft"]');
    $page->assertMissing('[data-availability-editor]');
    branchControlClick($page, 'button[wire\\:click="openPause"]');
    $page->assertValue('textarea[name="pause.reason"]', $originalReason ?? '');
    $page->resize(320, 844);
    $page->script("document.documentElement.style.zoom = '2'; document.querySelector('textarea[name=\"pause.reason\"]').scrollIntoView({ block: 'center' })");
    $page->assertScript("getComputedStyle(document.querySelector('[data-flux-header]')).position", 'static');
    $page->assertScript("document.querySelector('[data-flux-header]').getBoundingClientRect().bottom <= 0", true);
    $page->assertScript("(() => { const rect = document.querySelector('textarea[name=\"pause.reason\"]').getBoundingClientRect(); return rect.top >= 0 && rect.bottom <= window.innerHeight; })()", true);
    $page->assertScript("(() => { const rect = document.querySelector('[data-availability-editor]').getBoundingClientRect(); return rect.left >= 0 && rect.right <= window.innerWidth; })()", true);
    $page->assertScript("[...document.querySelectorAll('[data-availability-editor] button')].filter(button => button.getClientRects().length).every(button => button.getBoundingClientRect().right <= window.innerWidth && button.scrollWidth <= button.clientWidth)", true);
    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $page->screenshot(false, 'availability-pause-320-zoom2');
    $page->script("document.documentElement.style.zoom = ''");
    $page->resize(1440, 1000);
    $page->fill('textarea[name="pause.reason"]', 'Discard this second draft');

    branchControlChooseBranch($page, $otherBranch);
    $page->assertVisible('dialog[data-modal="availability-unsaved"]');
    branchControlClick($page, 'dialog[data-modal="availability-unsaved"] button[x-on\\:click="discardAndNavigate"]');
    $page->assertPathIs($otherAvailabilityPath)->assertSee($otherBranch->name);
    expect($branch->fresh()->temporary_closed_reason)->toBe($originalReason)->and($branch->fresh()->pause_version)->toBe(0);
    branchControlChooseBranch($page, $branch);
    $page->assertPathIs($availabilityPath);
    $page->assertMissing('dialog[data-modal="workspace-restaurant"][open]');

    $page->navigate(route('restaurant.dashboard', ['branch' => $branch->id], false))
        ->assertQueryStringHas('branch', (string) $branch->id);

    branchControlClick($page, '.workspace-restaurant__trigger');
    branchControlClick($page, 'dialog[data-modal="workspace-restaurant"] button[wire\\:click="chooseAggregate"]');
    $page->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->assertQueryStringHas('workspace', 'all')
        ->assertQueryStringMissing('branch')
        ->assertSee(__('dashboard.control.all_branches_description'))
        ->assertMissing('dialog[data-modal="workspace-restaurant"][open]');
    $page->navigate(route('restaurant.dashboard', ['branch' => $branch->id], false))
        ->assertQueryStringHas('branch', (string) $branch->id)
        ->assertQueryStringMissing('workspace')
        ->assertSee($branch->name)
        ->assertPresent('[data-dashboard-operations]')
        ->assertPresent('[data-dashboard-readiness]');

    $page->resize(390, 844);
    branchControlClick($page, '.workspace-restaurant__trigger');
    branchControlClick($page, 'dialog[data-modal="workspace-restaurant"] [data-flux-select-button]');
    $page->click('dialog[data-modal="workspace-restaurant"] input')
        ->keys('dialog[data-modal="workspace-restaurant"] input', 'ArrowDown')
        ->assertVisible('dialog[data-modal="workspace-restaurant"] ui-option[value="'.$branch->id.'"]');
    expect($page->script("Array.from(document.querySelectorAll('dialog[data-modal=\"workspace-restaurant\"] ui-option')).every(el => el.scrollHeight <= el.clientHeight + 1)"))->toBeTrue();
    $page->screenshot(true, 'branch-picker-390');
    copy(base_path('tests/Browser/Screenshots/branch-picker-390.png'), storage_path('logs/branch-picker-390.png'));
    $page->keys('dialog[data-modal="workspace-restaurant"]', 'Escape')
        ->assertMissing('dialog[data-modal="workspace-restaurant"][open]');
    $page->assertScript("document.activeElement.matches('.workspace-restaurant__trigger')", true);

    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        $page->resize($width, $height);
        expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        $page->screenshot(false, "branch-control-{$width}-light");
    }
    $page->script("document.documentElement.style.zoom = '2'");
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $page->script("document.documentElement.style.zoom = ''; localStorage.setItem('flux.appearance', 'dark')");
    foreach (['lt', 'ru'] as $locale) {
        $page->navigate(route('restaurant.dashboard', ['branch' => $branch->id, 'lang' => $locale], false))->resize(320, 844);
        $page->assertAttribute('html[lang]', 'lang', $locale)
            ->assertSee(__('navigation.menu', [], $locale))->assertSee(__('navigation.waiter', [], $locale));
        expect($page->script(<<<'JS'
            (() => {
                const link = document.querySelector('[data-quick-action-link][href*="/restaurant/waiter"]');
                const walker = document.createTreeWalker(link, NodeFilter.SHOW_TEXT, {
                    acceptNode: node => node.textContent.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP,
                });
                const text = walker.nextNode();
                if (!text) return 0;
                const range = document.createRange();
                range.selectNodeContents(text);
                return range.getClientRects().length;
            })()
            JS))->toBe(1);
        expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        $page->screenshot(true, "branch-control-320-{$locale}");
    }
    $page->navigate(route('restaurant.dashboard', ['branch' => $branch->id, 'lang' => 'en'], false))->resize(390, 844);
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $page->screenshot(false, 'branch-control-390-dark');
    $page->screenshot(true, 'branch-control-390-dark-full');
    $page->keys('body[class]', 'Tab');
    expect($page->script("document.activeElement.matches(':focus-visible')"))->toBeTrue();
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    branchControlClick($page, '.workspace-restaurant__trigger');
    branchControlSelectBranch($page, $otherBranch);
    $page->assertScript('document.querySelector(\'dialog[data-modal="workspace-restaurant"] [role="status"]\').getClientRects().length', 0);
    branchControlOffline($page, true);
    $page->assertDisabled('button[wire\\:click="refreshOperations"]');
    $page->assertDisabled('dialog[data-modal="workspace-restaurant"] input')
        ->assertDisabled('dialog[data-modal="workspace-restaurant"] button[type="submit"]')
        ->assertSee(__('workspace.offline'));
    $page->script('document.querySelector(\'dialog[data-modal="workspace-restaurant"] button[type="submit"]\').click()');
    $page->assertQueryStringHas('branch', (string) $branch->id);
    $page->keys('dialog[data-modal="workspace-restaurant"]', 'Escape')
        ->assertMissing('dialog[data-modal="workspace-restaurant"][open]');
    branchControlOffline($page, false);
    $page->assertEnabled('button[wire\\:click="refreshOperations"]');
    branchControlClick($page, '.workspace-restaurant__trigger');
    $page->assertEnabled('dialog[data-modal="workspace-restaurant"] input')
        ->assertEnabled('dialog[data-modal="workspace-restaurant"] button[type="submit"]')
        ->keys('dialog[data-modal="workspace-restaurant"]', 'Escape')
        ->assertMissing('dialog[data-modal="workspace-restaurant"][open]');

    branchControlClick($page, '[data-operation="pending"] a');
    $page->assertPathIs(route('restaurant.waiter.dashboard', absolute: false))->assertQueryStringHas('branch', (string) $branch->id)->assertQueryStringHas('attention', 'pending');
    $page->assertSee(__('operations.attention.pending'));
});

function branchControlChooseBranch(PendingAwaitablePage $page, Branch $branch): void
{
    branchControlClick($page, '.workspace-restaurant__trigger');
    branchControlSelectBranch($page, $branch);
    branchControlClick($page, 'dialog[data-modal="workspace-restaurant"] button[type="submit"]');
}

function branchControlSelectBranch(PendingAwaitablePage $page, Branch $branch): void
{
    $page->assertVisible('dialog[data-modal="workspace-restaurant"]')
        ->click('dialog[data-modal="workspace-restaurant"] [data-flux-select-button]')
        ->click('dialog[data-modal="workspace-restaurant"] input')
        ->fill('dialog[data-modal="workspace-restaurant"] input', $branch->name)
        ->keys('dialog[data-modal="workspace-restaurant"] input', 'ArrowDown');
    branchControlClick($page, 'dialog[data-modal="workspace-restaurant"] ui-option[value="'.$branch->id.'"]');
}

function branchControlClick(PendingAwaitablePage $page, string $selector): void
{
    $page->assertVisible($selector)->assertEnabled($selector);
    $encoded = json_encode($selector, JSON_THROW_ON_ERROR);
    $page->script("document.querySelector({$encoded}).click()");
    $page->wait(0.3);
}

function branchControlOffline(PendingAwaitablePage $page, bool $offline): void
{
    $guid = (new ReflectionProperty($page->page()->context(), 'guid'))->getValue($page->page()->context());
    expect($guid)->toBeString();
    foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
        // Consume the installed Playwright protocol response before observing the browser.
    }
}
