<?php

use App\Livewire\Auth\Login;
use Laravel\Fortify\Features;
use Livewire\Livewire;

test('login exposes the existing passkey protocol only when its feature is enabled', function (bool $enabled): void {
    if ($enabled) {
        $this->enableFortifyFeatures([Features::passkeys()]);
    }

    $response = $this->get(route('login'))->assertOk();

    if ($enabled) {
        $response->assertSee('x-data="passkeyVerification"', false)
            ->assertSee('data-options-url="'.route('passkey.login-options').'"', false)
            ->assertSee('data-submit-url="'.route('passkey.login').'"', false)
            ->assertSee('wire:submit="login"', false);
    } else {
        $response->assertDontSee('x-data="passkeyVerification"', false);
    }
})->with([false, true]);

test('an already mounted login rechecks the passkey feature when rendered again', function (): void {
    $this->enableFortifyFeatures([Features::passkeys()]);
    $component = Livewire::test(Login::class)->assertSee('x-data="passkeyVerification"', false);

    config(['fortify.features' => [Features::resetPasswords()]]);

    $component->call('$refresh')->assertDontSee('x-data="passkeyVerification"', false);
});
