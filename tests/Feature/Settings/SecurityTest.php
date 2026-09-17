<?php

use App\Livewire\Settings\Security;
use App\Livewire\Settings\TwoFactor\RecoveryCodes;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\PasskeyFactory;

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

test('security settings preserve JSON password confirmation responses', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('security.edit'))
        ->assertStatus(423)
        ->assertExactJson(['message' => 'Password confirmation required.']);
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
        ->update(calls: [['method' => 'updatePassword', 'params' => [], 'path' => '']], updates: [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->update(calls: [['method' => 'updatePassword', 'params' => [], 'path' => '']], updates: [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertHasErrors(['current_password']);
});

test('disabled two factor actions reject direct calls and preserve dormant credentials', function (string $action) {
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->refresh()->getRawOriginal();

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
    $credentials = $user->refresh()->getRawOriginal();
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

    $component->update(calls: [['method' => 'confirmTwoFactor', 'params' => [], 'path' => '']], updates: ['code' => $code])
        ->assertHasNoErrors()
        ->assertSet('twoFactorEnabled', true);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $component->call('disable')->assertSet('twoFactorEnabled', false);

    expect($user->refresh()->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull();
});

test('disabled recovery code component rejects access without changing dormant credentials', function () {
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->refresh()->getRawOriginal();

    Livewire::actingAs($user)->test(RecoveryCodes::class)->assertForbidden();

    expect($user->refresh()->getRawOriginal())->toBe($credentials);
});

test('recovery code regeneration rejects a feature disabled after mount', function () {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->refresh()->getRawOriginal();
    $component = Livewire::actingAs($user)->test(RecoveryCodes::class);

    config(['fortify.features' => [Features::resetPasswords()]]);

    $component->call('regenerateRecoveryCodes')->assertForbidden();

    expect($user->refresh()->getRawOriginal())->toBe($credentials);
});

test('enabled recovery codes can be displayed and regenerated', function () {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $oldCodes = $user->two_factor_recovery_codes;

    $component = Livewire::actingAs($user)->test(RecoveryCodes::class)
        ->assertSee('recovery-code-1')
        ->call('regenerateRecoveryCodes')
        ->assertHasNoErrors()
        ->assertDontSee('recovery-code-1');

    expect($user->refresh()->two_factor_recovery_codes)->not->toBe($oldCodes);
    expect($user->recoveryCodes())->toHaveCount(8)->not->toContain('recovery-code-1');
    foreach ($user->recoveryCodes() as $code) {
        $component->assertSee($code);
    }
});

test('recovery codes require configured two factor authentication', function () {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(RecoveryCodes::class)->assertForbidden();

    expect($user->refresh()->two_factor_recovery_codes)->toBeNull();
});

test('enabled passkeys can be listed and deleted by their owner', function () {
    $this->enableFortifyFeatures([Features::passkeys()]);
    $user = User::factory()->create();
    $passkey = PasskeyFactory::new()->for($user)->create();

    Livewire::actingAs($user)->test(Security::class)
        ->assertSee($passkey->name)
        ->call('confirmDelete', $passkey->id)
        ->assertSet('deletingPasskeyId', $passkey->id)
        ->call('deletePasskey')
        ->assertHasNoErrors()
        ->assertSet('passkeys', []);

    $this->assertModelMissing($passkey);
});

test('enabled passkeys cannot be selected by another user', function () {
    $this->enableFortifyFeatures([Features::passkeys()]);
    $passkey = PasskeyFactory::new()->create();
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Security::class);

    expect(fn () => $component->call('confirmDelete', $passkey->id))
        ->toThrow(ModelNotFoundException::class);

    $this->assertModelExists($passkey);
});

test('passkey deletion rechecks the current feature configuration', function () {
    $this->enableFortifyFeatures([Features::passkeys()]);
    $user = User::factory()->create();
    $passkey = PasskeyFactory::new()->for($user)->create();
    $component = Livewire::actingAs($user)->test(Security::class)
        ->call('confirmDelete', $passkey->id);

    config(['fortify.features' => [Features::resetPasswords()]]);

    $component->call('deletePasskey')->assertForbidden();

    $this->assertModelExists($passkey);
});

test('security mutations reapply password confirmation on real Livewire updates', function (
    string $componentName,
    string $action,
    bool $expired,
    string $accept,
) {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->refresh()->getRawOriginal();
    $page = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('security.edit'))
        ->assertOk();

    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = collect($matches[1])
        ->map(fn (string $encoded): string => html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5))
        ->first(fn (string $candidate): bool => json_decode($candidate, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $componentName);

    expect($snapshot)->toBeString()->not->toBeEmpty();

    if ($expired) {
        $this->withSession([
            'auth.password_confirmed_at' => now()->subSeconds(config('auth.password_timeout') + 1)->timestamp,
        ]);
    }

    $response = $this->postJson(route('default-livewire.update'), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['method' => $action, 'params' => []]],
        ]],
    ], ['X-Livewire' => '', 'Accept' => $accept]);

    if ($expired) {
        if ($accept === 'application/json') {
            $response->assertStatus(423)
                ->assertExactJson(['message' => 'Password confirmation required.']);
        } else {
            $response->assertRedirect(route('password.confirm'));
        }

        expect($user->refresh()->getRawOriginal())->toBe($credentials);

        return;
    }

    $response->assertOk();
    expect($user->refresh()->two_factor_recovery_codes)->not->toBe($credentials['two_factor_recovery_codes']);
})->with([
    'security page' => ['settings.security', 'disable'],
    'recovery codes child' => ['settings.two-factor.recovery-codes', 'regenerateRecoveryCodes'],
])->with([
    'recently confirmed' => false,
    'confirmation expired' => true,
])->with([
    'JSON response requested' => 'application/json',
    'browser Livewire headers' => '*/*',
]);
