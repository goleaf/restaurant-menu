<?php

use App\Livewire\Settings\Security;
use App\Livewire\Settings\TwoFactor\RecoveryCodes;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'));

    $response->assertOk();

    $response->assertSee('Update password');
    $response->assertDontSee('Manage your passkeys for passwordless sign-in');
    $response->assertDontSee('Add a passkey to sign in without a password');
    $response->assertDontSee('Two-factor authentication');
});

test('security settings page requires password confirmation when enabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security settings page renders without two factor when feature is disabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Update password')
        ->assertDontSee('Manage your passkeys for passwordless sign-in')
        ->assertDontSee('Add a passkey to sign in without a password')
        ->assertDontSee('Two-factor authentication');
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ])]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user);

    $component = Livewire::test(Security::class);

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});

test('disabled two factor actions reject direct calls and preserve dormant credentials', function (string $action) {
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->getRawOriginal();

    Livewire::actingAs($user)->test(Security::class)
        ->call($action)
        ->assertForbidden();

    expect($user->refresh()->getRawOriginal())->toBe($credentials);
})->with(['enable', 'confirmTwoFactor', 'disable']);

test('disabled passkey actions reject direct calls', function (string $action, array $arguments) {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Security::class)
        ->call($action, ...$arguments)
        ->assertForbidden();
})->with([
    'list' => ['loadPasskeys', []],
    'select for deletion' => ['confirmDelete', [1]],
    'delete' => ['deletePasskey', []],
]);

test('two factor mutations recheck the current feature configuration', function () {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->getRawOriginal();
    $component = Livewire::actingAs($user)->test(Security::class);

    config(['fortify.features' => [Features::resetPasswords()]]);

    $component->call('disable')->assertForbidden();

    expect($user->refresh()->getRawOriginal())->toBe($credentials);
});

test('enabled two factor authentication can be configured confirmed and disabled', function () {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Security::class)
        ->call('enable')
        ->assertHasNoErrors()
        ->assertSet('showModal', true)
        ->assertSet('twoFactorEnabled', false);

    $secret = decrypt($user->refresh()->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $component->set('code', $code)
        ->call('confirmTwoFactor')
        ->assertHasNoErrors()
        ->assertSet('twoFactorEnabled', true);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $component->call('disable')->assertSet('twoFactorEnabled', false);

    expect($user->refresh()->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull();
});

test('disabled recovery code component rejects access without changing dormant credentials', function () {
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->getRawOriginal();

    Livewire::actingAs($user)->test(RecoveryCodes::class)->assertForbidden();

    expect($user->refresh()->getRawOriginal())->toBe($credentials);
});

test('recovery code regeneration rejects a feature disabled after mount', function () {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->getRawOriginal();
    $component = Livewire::actingAs($user)->test(RecoveryCodes::class);

    config(['fortify.features' => [Features::resetPasswords()]]);

    $component->call('regenerateRecoveryCodes')->assertForbidden();

    expect($user->refresh()->getRawOriginal())->toBe($credentials);
});

test('enabled recovery codes can be displayed and regenerated', function () {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $oldCodes = $user->two_factor_recovery_codes;

    Livewire::actingAs($user)->test(RecoveryCodes::class)
        ->assertSet('recoveryCodes', ['recovery-code-1'])
        ->call('regenerateRecoveryCodes')
        ->assertHasNoErrors()
        ->assertSet('recoveryCodes', fn (array $codes): bool => count($codes) === 8 && ! in_array('recovery-code-1', $codes, true));

    expect($user->refresh()->two_factor_recovery_codes)->not->toBe($oldCodes);
});
