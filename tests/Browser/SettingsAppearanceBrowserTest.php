<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Support\IsolatedBrowserIdentity;

test('profile appearance retains theme preferences drafts and accessible responsive controls', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $user = User::factory()->create(['password' => 'password']);
    $page = visit(route('login', absolute: false));
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->navigate(route('appearance.edit', absolute: false))
        ->assertPathIs(route('profile.edit', absolute: false))
        ->assertVisible('#profile-appearance')
        ->assertMissing('a[href="'.route('appearance.edit').'"]');

    $light = '#profile-appearance [role="radio"][value="light"]';
    $dark = '#profile-appearance [role="radio"][value="dark"]';
    $system = '#profile-appearance [role="radio"][value="system"]';
    $page->fill('input[wire\\:model="name"]', 'Unfinished profile name')
        ->click($dark)
        ->assertScript('document.documentElement.classList.contains("dark")')
        ->assertAttribute($dark, 'aria-checked', 'true')
        ->assertValue('input[wire\\:model="name"]', 'Unfinished profile name');
    expect($user->refresh()->name)->not->toBe('Unfinished profile name');

    $page->click('form[wire\\:submit="updateProfileInformation"] button[type="submit"]')
        ->assertSee(__('ui.livewire.settings.profile.profile_updated'))
        ->assertAttribute($dark, 'aria-checked', 'true');
    expect($user->refresh()->name)->toBe('Unfinished profile name');

    $page->refresh()
        ->assertAttribute($dark, 'aria-checked', 'true')
        ->assertScript('document.documentElement.classList.contains("dark")')
        ->keys($dark, 'ArrowLeft')
        ->assertAttribute($light, 'aria-checked', 'true')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->click($system)
        ->assertAttribute($system, 'aria-checked', 'true')
        ->assertScript('document.documentElement.classList.contains("dark") === matchMedia("(prefers-color-scheme: dark)").matches');

    foreach (['en', 'lt', 'ru'] as $locale) {
        $page->navigate(route('profile.edit', ['lang' => $locale], false));
        foreach ([320, 768, 1440] as $width) {
            $page->resize($width, 1000)
                ->assertVisible($light)->assertVisible($dark)->assertVisible($system)
                ->assertAttribute('#profile-appearance [role="radiogroup"]', 'aria-labelledby', 'profile-appearance-heading')
                ->assertScript(<<<'JS'
                    (() => {
                        const section = document.querySelector('#profile-appearance').getBoundingClientRect();
                        return [...document.querySelectorAll('#profile-appearance [role="radio"]')].every(control => {
                            const bounds = control.getBoundingClientRect();
                            return bounds.height >= 44 && bounds.left >= section.left && bounds.right <= section.right + 1
                                && control.scrollWidth <= control.clientWidth;
                        });
                    })()
                    JS)
                ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
        }
    }

    $page->resize(390, 844)->click($dark)->screenshot(filename: 'profile-appearance-mobile-dark');
    $page->resize(1440, 1000)->click($light)->screenshot(filename: 'profile-appearance-desktop-light');
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
