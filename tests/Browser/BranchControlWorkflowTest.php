<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\DemoRestaurantSeeder;
use Pest\Browser\Api\PendingAwaitablePage;

test('branch control preserves URL history local periods and ordering drafts across responsive themes', function (): void {
    $this->withVite();
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['restaurant-menu.test', '127.0.0.1', 'localhost']);
    $this->seed(DemoRestaurantSeeder::class);
    $owner = DemoAccountCatalog::forRole(SystemRole::Owner);
    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $branch = Branch::query()->where('organization_id', $organization->id)->firstOrFail();
    $branch->forceFill(['name' => 'Vilniaus senamiesčio restoranas ir šeimos terasa'])->save();
    $page = visit(route('demo-login.index', absolute: false));
    branchControlClick($page, sprintf('form[action$="/demo-login/%s"] button[type="submit"]', $owner['role']->value));
    $page->assertPathIs(route('dashboard', absolute: false));
    $page->navigate(route('restaurant.dashboard', absolute: false));
    $page->assertAttribute('ui-radio-group[name="selectedBranchId"] ui-radio[value=""]', 'aria-checked', 'true');
    $page->navigate(route('restaurant.dashboard', ['branch' => $branch->id], false));
    $page->assertSee($branch->name)->assertPresent('[data-dashboard-operations]')->assertPresent('[data-dashboard-readiness]');
    branchControlClick($page, '[data-dashboard-branch-picker] summary');
    $page->fill('input[name="branchSearch"]', 'No matching restaurant');
    $page->assertSee(__('dashboard.control.branch_search_empty'))
        ->assertAttribute('ui-radio[value="'.$branch->id.'"]', 'aria-checked', 'true');
    $page->fill('input[name="branchSearch"]', '');
    $page->keys('input[name="branchSearch"]', 'Escape');
    expect($page->script("document.querySelector('[data-dashboard-branch-picker]').open"))->toBeFalse();
    expect($page->script("document.activeElement.matches('[data-dashboard-branch-picker] summary')"))->toBeTrue();
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
    branchControlClick($page, '[data-ordering-controls] summary');
    $page->fill('input[name="closure.temporaryClosedReason"]', 'Browser unsaved reason');
    branchControlClick($page, '[data-dashboard-branch-picker] summary');
    branchControlClick($page, 'ui-radio-group[name="selectedBranchId"] ui-radio[value=""]');
    $page->assertSee(__('dashboard.control.unsaved_ordering'))->assertQueryStringHas('branch', (string) $branch->id);
    branchControlClick($page, 'button[wire\\:click="discardOrdering"]');

    branchControlClick($page, '[data-dashboard-branch-picker] summary');
    branchControlClick($page, 'ui-radio-group[name="selectedBranchId"] ui-radio[value=""]');
    $page->assertSee(__('dashboard.control.all_branches_description'));
    branchControlClick($page, '[data-dashboard-branch-picker] summary');
    branchControlClick($page, 'ui-radio-group[name="selectedBranchId"] ui-radio[value="'.$branch->id.'"]');
    $page->assertQueryStringHas('branch', (string) $branch->id);
    expect($page->script("document.querySelector('[data-dashboard-branch-picker]').open"))->toBeFalse();
    expect($page->script("document.activeElement.matches('[data-dashboard-branch-picker] summary')"))->toBeTrue();

    $page->resize(390, 844);
    branchControlClick($page, '[data-dashboard-branch-picker] summary');
    expect($page->script("Array.from(document.querySelectorAll('ui-radio-group[name=\"selectedBranchId\"] ui-radio')).every(el => el.scrollHeight <= el.clientHeight + 1)"))->toBeTrue();
    $page->screenshot(true, 'branch-picker-390');
    copy(base_path('tests/Browser/Screenshots/branch-picker-390.png'), storage_path('logs/branch-picker-390.png'));
    $page->keys('input[name="branchSearch"]', 'Escape');

    $page->script("document.querySelector('[data-ordering-controls]').open = false");
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
    $page->script("window.dispatchEvent(new Event('offline'))");
    $page->assertDisabled('button[wire\\:click="refreshOperations"]');
    branchControlClick($page, '[data-dashboard-branch-picker] summary');
    $page->assertDisabled('input[name="branchSearch"]')
        ->assertAttribute('ui-radio-group[name="selectedBranchId"]', 'aria-disabled', 'true');
    expect($page->script("Array.from(document.querySelectorAll('ui-radio-group[name=\"selectedBranchId\"] ui-radio')).every(el => el.disabled)"))->toBeTrue();
    $page->script("document.querySelector('ui-radio-group[name=\"selectedBranchId\"] ui-radio[value=\"\"]').click()");
    $page->assertQueryStringHas('branch', (string) $branch->id);
    $page->keys('[data-dashboard-branch-picker] summary', 'Escape');
    expect($page->script("document.querySelector('[data-dashboard-branch-picker]').open"))->toBeFalse();
    $page->script("window.dispatchEvent(new Event('online'))");
    $page->assertEnabled('button[wire\\:click="refreshOperations"]')
        ->assertAttribute('ui-radio-group[name="selectedBranchId"]', 'aria-disabled', 'false');

    branchControlClick($page, '[data-operation="pending"] a');
    $page->assertPathIs(route('restaurant.waiter.dashboard', absolute: false))->assertQueryStringHas('branch', (string) $branch->id)->assertQueryStringHas('attention', 'pending');
    $page->assertSee(__('operations.attention.pending'));
});

function branchControlClick(PendingAwaitablePage $page, string $selector): void
{
    $page->assertVisible($selector)->assertEnabled($selector);
    $encoded = json_encode($selector, JSON_THROW_ON_ERROR);
    $page->script("document.querySelector({$encoded}).click()");
    $page->wait(0.3);
}
