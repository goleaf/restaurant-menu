<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureDemoLoginIsEnabled;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['web', EnsureDemoLoginIsEnabled::class])
        ->get('/__demo-login-probe', fn () => response('demo-enabled'));
});

test('demo login is hidden when it is disabled', function (): void {
    config()->set('demo-login.enabled', false);

    $this->get('/__demo-login-probe')->assertNotFound();
});

test('demo login is hidden in production even when it is enabled', function (): void {
    config()->set('demo-login.enabled', true);
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->get('/__demo-login-probe')->assertNotFound();
});

test('demo login is available when it is enabled outside production', function (): void {
    config()->set('demo-login.enabled', true);

    $this->get('/__demo-login-probe')
        ->assertOk()
        ->assertSeeText('demo-enabled');
});
