<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Support\IsolatedBrowserIdentity;

test('local directory signs in by mouse and keyboard without filling the password form', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->app->detectEnvironment(fn (): string => 'local');
    config()->set([
        'app.env' => 'local',
        'demo-login.enabled' => true,
        'demo-login.allowed_hosts' => ['127.0.0.1', 'localhost'],
        'demo-login.password' => null,
    ]);
    $first = User::factory()->create(['name' => 'Local Browser User One']);
    $second = User::factory()->create(['name' => 'Local Browser User Two']);
    $page = visit(route('login', absolute: false));

    foreach (['en', 'lt', 'ru'] as $locale) {
        $page->navigate(route('login', ['lang' => $locale], false));
        foreach ([320, 1440] as $width) {
            $page->resize($width, 1000)
                ->assertVisible('@local-login-user-'.$first->id)
                ->assertScript(<<<'JS'
                    (() => {
                        return document.documentElement.scrollWidth <= window.innerWidth
                            && [...document.querySelectorAll('[data-test^="local-login-user-"]')].every(button => {
                                const bounds = button.getBoundingClientRect();
                                return bounds.height >= 44 && button.scrollWidth <= button.clientWidth
                                    && button.form.hasAttribute('wire:submit') && !button.form.hasAttribute('action') && button.getAttribute('aria-label').includes(button.textContent.trim());
                            });
                    })()
                    JS);
        }
    }

    $page->resize(390, 844)->screenshot(filename: 'local-user-login-mobile')
        ->assertValue('input[name="email"]', '')
        ->assertValue('input[name="password"]', '')
        ->click('@local-login-user-'.$first->id)
        ->assertPathIs('/dashboard');
    $page->navigate(route('profile.edit', absolute: false))
        ->assertValue('input[wire\\:model="email"]', $first->email)
        ->click('@sidebar-menu-button')->click('@logout-button')
        ->assertPathIs(route('home', absolute: false))
        ->navigate(route('login', absolute: false))
        ->keys('@local-login-user-'.$second->id, 'Enter')
        ->assertPathIs('/dashboard')
        ->navigate(route('profile.edit', absolute: false))
        ->assertValue('input[wire\\:model="email"]', $second->email)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
