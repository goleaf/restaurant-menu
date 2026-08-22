<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('public registration is disabled while password reset remains enabled', function () {
    expect(config('fortify.features'))
        ->toContain(Features::resetPasswords())
        ->not->toContain(Features::registration())
        ->not->toContain(Features::emailVerification())
        ->not->toContain(Features::twoFactorAuthentication())
        ->not->toContain(Features::passkeys());

    expect(Route::has('login'))->toBeTrue();
    expect(Route::has('register'))->toBeFalse();
    expect(Route::has('register.store'))->toBeFalse();
    expect(Route::has('invitations.register'))->toBeTrue();
    expect(Route::has('logout'))->toBeTrue();
    expect(Route::has('password.request'))->toBeTrue();
    expect(Route::has('password.reset'))->toBeTrue();
    expect(Route::has('two-factor.login'))->toBeFalse();
    expect(Route::has('passkey.login'))->toBeFalse();
    expect(Route::has('verification.notice'))->toBeFalse();
});

test('dashboard zones require authentication', function (string $routeName) {
    $this->get(route($routeName))
        ->assertRedirect(route('login'));
})->with([
    'overview' => 'dashboard',
    'restaurant' => 'restaurant.dashboard',
    'superadmin' => 'superadmin.dashboard',
]);

test('authenticated users can access restaurant zones but not platform zones', function () {
    $user = User::factory()->create([
        'name' => 'Basic User',
        'email' => 'basic@example.com',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('restaurant.dashboard'))->assertOk();
    $this->get(route('superadmin.dashboard'))->assertForbidden();
});

test('password reset uses local mail configuration and can be requested', function () {
    Notification::fake();

    expect(config('mail.default'))->toBeIn(['array', 'log']);
    expect(array_keys(config('mail.mailers')))
        ->toContain('array')
        ->toContain('log')
        ->not->toContain('mailgun')
        ->not->toContain('postmark')
        ->not->toContain('ses')
        ->not->toContain('resend');

    $user = User::factory()->create();

    $this->post(route('password.request'), [
        'email' => $user->email,
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('basic profile page is available to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Profile');
});
