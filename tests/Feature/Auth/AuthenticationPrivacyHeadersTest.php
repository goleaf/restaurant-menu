<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;

beforeEach(function (): void {
    config([
        'fortify.prefix' => 'account',
        'fortify.paths.login' => '/sign-in',
        'fortify.paths.password.reset' => '/recover/{token}',
    ]);
    $this->enableFortifyFeatures([Features::resetPasswords()]);
});

test('configured authentication page paths retain private response headers', function (): void {
    $response = $this->get('/account/sign-in')
        ->assertOk()
        ->assertSeeLivewire(Login::class)
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
});

test('configured password reset credential handoff retains private headers without rendering the token', function (): void {
    $user = User::factory()->create();
    $token = Password::broker(config('fortify.passwords'))->createToken($user);

    $response = $this->get('/account/recover/'.$token.'?email='.rawurlencode($user->email))
        ->assertRedirect(route('password.reset.form'))
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    expect($response->getContent())->not->toContain($token);
});

test('configured password reset rejection retains private headers', function (): void {
    $response = $this->get('/account/recover/invalid-token')
        ->assertNotFound()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
});
