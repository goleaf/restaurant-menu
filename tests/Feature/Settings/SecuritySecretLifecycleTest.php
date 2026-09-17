<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

function securitySecretSnapshot(string $route, string $component): string
{
    $response = test()->get(route($route))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5);
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException('Missing settings component snapshot.');
}

/** @param array<string, mixed> $updates */
function securitySecretCall(string $snapshot, string $method, array $updates): TestResponse
{
    return test()->withCredentials()->withCookie(config('session.cookie'), session()->getId())
        ->postJson(route('default-livewire.update'), ['components' => [[
            'snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['method' => $method, 'params' => []]],
        ]]], ['X-Livewire' => '', 'X-CSRF-TOKEN' => session()->token()]);
}

test('security settings erase pending credentials on refresh and unrelated actions', function (string $method): void {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $values = [
        'current_password' => 'pending-current-secret',
        'password' => 'pending-new-secret',
        'password_confirmation' => 'pending-confirmation-secret',
        'code' => '123456',
    ];

    $response = securitySecretCall(securitySecretSnapshot('security.edit', 'settings.security'), $method, $values)->assertOk();
    $snapshot = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);

    foreach ($values as $field => $value) {
        expect($snapshot['data'][$field])->toBe('');
        expect($response->getContent())->not->toContain($value);
    }
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with(['$refresh', 'closeDeleteModal']);

test('failed two factor setup confirmation does not retain the submitted code', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $credentials = $user->fresh()->getRawOriginal();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);

    $response = securitySecretCall(securitySecretSnapshot('security.edit', 'settings.security'), 'confirmTwoFactor', ['code' => 'broken'])->assertOk();
    $snapshot = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);

    expect($snapshot['memo']['errors'])->toHaveKey('code');
    expect($snapshot['data']['code'])->toBe('');
    expect($response->getContent())->not->toContain('broken');
    expect($user->fresh()->getRawOriginal())->toBe($credentials);
});

test('delete account clears its password on refresh and rejected submission', function (string $method): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = securitySecretCall(securitySecretSnapshot('profile.edit', 'settings.delete-user-form'), $method, ['password' => 'incorrect-private-password'])->assertOk();
    $snapshot = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);

    expect($snapshot['data']['password'])->toBe('');
    expect($response->getContent())->not->toContain('incorrect-private-password');
    if ($method === 'deleteUser') {
        expect($snapshot['memo']['errors'])->toHaveKey('password');
    }
    $this->assertAuthenticatedAs($user);
    $this->assertModelExists($user);
})->with(['$refresh', 'deleteUser']);

test('settings credential validation rejects malformed transport input without retaining it', function (string $route, string $component, string $method, string $field): void {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);

    $response = securitySecretCall(securitySecretSnapshot($route, $component), $method, [$field => ['malformed-private-value']])->assertOk();
    $snapshot = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);

    expect($snapshot['memo']['errors'])->toHaveKey($field);
    expect($snapshot['data'][$field])->toBe('');
    expect($response->getContent())->not->toContain('malformed-private-value');
    $this->assertModelExists($user);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    ['security.edit', 'settings.security', 'updatePassword', 'current_password'],
    ['profile.edit', 'settings.delete-user-form', 'deleteUser', 'password'],
]);

test('deleting an account requires full navigation after invalidating its session', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $snapshot = securitySecretSnapshot('profile.edit', 'settings.delete-user-form');
    $sessionId = session()->getId();
    $csrfToken = session()->token();

    $response = securitySecretCall($snapshot, 'deleteUser', ['password' => 'password'])->assertOk();

    $response->assertJsonPath('components.0.effects.redirect', '/')
        ->assertJsonMissingPath('components.0.effects.redirectUsingNavigate');
    $this->assertGuest();
    $this->assertModelMissing($user);
    expect(session()->getId())->not->toBe($sessionId);
    expect(session()->token())->not->toBe($csrfToken);
});

test('two factor setup presentation survives its steps without serializing the setup secret', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = securitySecretSnapshot('security.edit', 'settings.security');
    $secret = null;

    foreach (['enable' => true, '$refresh' => true, 'showVerificationIfNecessary' => false, 'resetVerification' => true, 'closeModal' => false] as $method => $showsSetup) {
        $response = securitySecretCall($snapshot, $method, [])->assertOk();
        $secret ??= decrypt($user->fresh()->two_factor_secret);
        $snapshot = $response->json('components.0.snapshot');
        $data = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['data'];

        expect($data)->not->toHaveKey('manualSetupKey')->not->toHaveKey('qrCodeSvg');
        expect($snapshot)->not->toContain($secret);
        expect(str_contains($response->json('components.0.effects.html'), $secret))->toBe($showsSetup);
    }

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('two factor setup refresh does not disclose a secret after its feature is disabled', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $response = securitySecretCall(securitySecretSnapshot('security.edit', 'settings.security'), 'enable', [])->assertOk();
    $secret = decrypt($user->fresh()->two_factor_secret);

    config(['fortify.features' => [Features::resetPasswords()]]);

    securitySecretCall($response->json('components.0.snapshot'), '$refresh', [])->assertOk()->assertDontSee($secret);
});

test('recovery codes are rendered and regenerated without entering component snapshots', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = securitySecretSnapshot('security.edit', 'settings.two-factor.recovery-codes');

    expect(json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['data'])->not->toHaveKey('recoveryCodes');
    expect($snapshot)->not->toContain('recovery-code-1');

    foreach (['$refresh', 'regenerateRecoveryCodes', '$refresh'] as $method) {
        $response = securitySecretCall($snapshot, $method, [])->assertOk();
        $snapshot = $response->json('components.0.snapshot');

        expect(json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['data'])->not->toHaveKey('recoveryCodes');
        foreach ($user->fresh()->recoveryCodes() as $code) {
            expect($response->json('components.0.effects.html'))->toContain($code);
            expect($snapshot)->not->toContain($code);
        }
    }

    expect($user->fresh()->recoveryCodes())->toHaveCount(8)->not->toContain('recovery-code-1');
});

test('recovery code presentation rejects a snapshot from a previous authenticated actor', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $original = User::factory()->withTwoFactor()->create();
    $replacement = User::factory()->withTwoFactor()->create();
    $originalCodes = $original->two_factor_recovery_codes;
    $replacementCodes = $replacement->two_factor_recovery_codes;
    $this->actingAs($original)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = securitySecretSnapshot('security.edit', 'settings.two-factor.recovery-codes');

    $this->actingAs($replacement);

    securitySecretCall($snapshot, 'regenerateRecoveryCodes', [])->assertStatus(409)->assertDontSee('recovery-code-1');
    expect($original->fresh()->two_factor_recovery_codes)->toBe($originalCodes);
    expect($replacement->fresh()->two_factor_recovery_codes)->toBe($replacementCodes);
});
