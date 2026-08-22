<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('public registration is disabled and account creation is invitation only', function () {
    expect(config('fortify.features'))->not->toContain(Features::registration())
        ->and(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse()
        ->and(Route::has('invitations.register'))->toBeTrue();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Uninvited User',
        'email' => 'uninvited@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee(__('invitations.account.invite_only'))
        ->assertDontSee(__('ui.auth.login.sign_up'));

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'uninvited@example.test']);
});
