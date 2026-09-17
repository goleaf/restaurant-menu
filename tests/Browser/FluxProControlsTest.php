<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Tests\Support\IsolatedBrowserIdentity;

test('the installed Pro reference supports real selection keyboard and editor interactions', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create(['email' => 'pro-controls@example.test', 'password' => 'password']);
    $user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());

    $page = visit(route('login', absolute: false));
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->navigate(route('local.components', absolute: false))->resize(1440, 1000)
        ->assertPresent('[data-pro-reference-catalog]');

    $page->click('[data-pro-reference="select"] button[role="combobox"]')
        ->click('[data-pro-reference="select"] ui-option[value="card_terminal"]')
        ->assertScript('document.querySelector("[data-pro-reference=select]").value', 'card_terminal');

    $page->click('[data-pro-reference="pillbox"] ui-pillbox-trigger')
        ->click('[data-pro-reference="pillbox"] ui-option[value="ready"]')
        ->assertScript('document.querySelector("[data-pro-reference=pillbox]").value', ['ready']);

    $page->fill('[data-pro-reference="autocomplete"]', __('ui.reference.example_name'))
        ->keys('[data-pro-reference="autocomplete"]', 'ArrowDown')
        ->keys('[data-pro-reference="autocomplete"]', 'Enter')
        ->assertValue('[data-pro-reference="autocomplete"]', __('ui.reference.example_name'));

    $page->click('[data-pro-reference="tabs"] [role="tab"][name="reference-details"]')
        ->keys('[data-pro-reference="tabs"] [role="tab"][name="reference-details"]', 'ArrowRight')
        ->assertAttribute('[data-pro-reference="tabs"] [role="tab"][name="reference-history"]', 'aria-selected', 'true')
        ->keys('[data-pro-reference="tabs"] [role="tab"][name="reference-history"]', 'ArrowLeft')
        ->assertAttribute('[data-pro-reference="tabs"] [role="tab"][name="reference-details"]', 'aria-selected', 'true');

    $page->click('[data-pro-reference="accordion"] button')
        ->assertAttribute('[data-pro-reference="accordion"] button', 'aria-expanded', 'true');

    $page->keys('[data-pro-reference="slider"] input[type="range"]', 'ArrowRight')
        ->assertScript('Number(document.querySelector("[data-pro-reference=slider]").value)', 51);

    $page->fill('[data-pro-reference="composer"] textarea', 'First line')
        ->keys('[data-pro-reference="composer"] textarea', 'Shift+Enter')
        ->assertValue('[data-pro-reference="composer"] textarea', "First line\n");

    $page->assertPresent('[data-pro-reference="editor"] [contenteditable="true"]')
        ->assertScript('document.querySelectorAll("script[src*=\"/flux/editor\"]").length', 1)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    $page->click('[data-pro-reference="command"] input')
        ->keys('[data-pro-reference="command"] input', 'ArrowDown')
        ->keys('[data-pro-reference="command"] input', 'Enter')
        ->assertVisible('dialog[data-modal="component-reference-edit"]');
    $page->click('dialog[data-modal="component-reference-edit"] button[autofocus]')
        ->assertMissing('dialog[data-modal="component-reference-edit"][open]');
    $page->click('[data-pro-reference="command"] ui-option')
        ->assertVisible('dialog[data-modal="component-reference-edit"]')
        ->click('dialog[data-modal="component-reference-edit"] button[autofocus]')
        ->assertMissing('dialog[data-modal="component-reference-edit"][open]');

    foreach (['lt', 'ru'] as $locale) {
        $page->navigate(route('local.components', ['lang' => $locale], false));
        $page->assertAttribute('[data-pro-reference="calendar"] ui-calendar-previous', 'aria-label', __('ui.accessibility.previous_month', [], $locale))
            ->assertAttribute('[data-pro-reference="calendar"] ui-calendar-next', 'aria-label', __('ui.accessibility.next_month', [], $locale));
        $page->click('[data-pro-reference="calendar"] ui-calendar-next')
            ->click('[data-pro-reference="calendar"] ui-calendar-previous');
        $page->click('[data-pro-reference="select"] button[role="combobox"]')
            ->fill('[data-pro-reference="select"] [data-flux-select-search] input', 'zzzz');
        $page->click('[data-pro-reference="select"] [data-flux-select-search] button')
            ->assertValue('[data-pro-reference="select"] [data-flux-select-search] input', '')
            ->assertAttribute('[data-pro-reference="select"] [data-flux-select-search] button', 'aria-label', __('ui.accessibility.clear_search', [], $locale))
            ->keys('[data-pro-reference="select"] [data-flux-select-search] input', 'Escape');
    }

    $page->navigate(route('profile.edit', absolute: false))
        ->navigate(route('local.components', absolute: false))
        ->assertPresent('[data-pro-reference="editor"] [contenteditable="true"]')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
