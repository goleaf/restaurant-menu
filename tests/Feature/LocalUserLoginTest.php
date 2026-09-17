<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');
    config()->set([
        'app.env' => 'local',
        'demo-login.enabled' => true,
        'demo-login.allowed_hosts' => ['restaurant-menu.test'],
        'demo-login.password' => null,
    ]);
});

function localIdentitySnapshot(): string
{
    $response = test()->get('https://restaurant-menu.test/login')->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5);
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === 'local.user-login') {
            return $snapshot;
        }
    }
    throw new RuntimeException('Missing local login component snapshot.');
}

function localIdentityCall(string $snapshot, mixed $userId, bool $csrf = true, string $host = 'restaurant-menu.test'): TestResponse
{
    return test()->withCredentials()->withCookie(config('session.cookie'), session()->getId())->postJson('https://'.$host.route('default-livewire.update', absolute: false), [
        'components' => [[
            'snapshot' => $snapshot, 'updates' => [],
            'calls' => [['method' => 'login', 'params' => [$userId]]],
        ]],
    ], ['X-Livewire' => '', ...($csrf ? ['X-CSRF-TOKEN' => session()->token()] : [])]);
}

test('each local directory identity has its own Livewire login button', function (): void {
    $users = User::factory()->count(2)->create();
    $response = $this->get('https://restaurant-menu.test/login')->assertOk();
    foreach ($users as $user) {
        $response->assertSee('wire:submit="login('.$user->id.')"', false)
            ->assertSee('data-test="local-login-user-'.$user->id.'"', false)
            ->assertSee(__('local_login.sign_in_as', ['name' => $user->name]));
    }
    $response->assertDontSee('action="https://restaurant-menu.test/local-login/', false);
});

test('a local directory click logs in the selected ordinary account without a password or account changes', function (): void {
    $other = User::factory()->create();
    $selected = User::factory()->create();
    $original = $selected->fresh()->getRawOriginal();
    $snapshot = localIdentitySnapshot();
    session()->put(['auth.password_confirmed_at' => time(), 'login.id' => $other->id, 'login.remember' => true]);
    $previousSession = session()->getId();

    localIdentityCall($snapshot, $selected->id)->assertOk()
        ->assertJsonPath('components.0.effects.redirect', route('dashboard'))
        ->assertSessionMissing('auth.password_confirmed_at')
        ->assertSessionMissing('login.id')->assertSessionMissing('login.remember')
        ->assertHeaderContains('Cache-Control', 'no-store')->assertHeader('Referrer-Policy', 'no-referrer');

    $this->assertAuthenticatedAs($selected);
    expect(session()->getId())->not->toBe($previousSession)
        ->and($selected->refresh()->getRawOriginal())->toBe($original);
    $this->get(route('superadmin.dashboard'))->assertForbidden();
});

test('local account action fails closed outside the directory boundary even with a signed snapshot', function (string $environment, string $configuredEnvironment, bool $enabled, string $host): void {
    $user = User::factory()->create();
    $snapshot = localIdentitySnapshot();
    $this->app->detectEnvironment(fn (): string => $environment);
    config()->set(['app.env' => $configuredEnvironment, 'demo-login.enabled' => $enabled]);
    localIdentityCall($snapshot, $user->id, host: $host)->assertNotFound();
    $this->assertGuest();
})->with([
    ['production', 'local', true, 'restaurant-menu.test'],
    ['local', 'production', true, 'restaurant-menu.test'],
    ['staging', 'local', true, 'restaurant-menu.test'],
    ['local', 'staging', true, 'restaurant-menu.test'],
    ['testing', 'local', true, 'restaurant-menu.test'],
    ['local', 'local', false, 'restaurant-menu.test'],
    ['local', 'local', true, 'foreign.test'],
]);

test('local account action rejects missing csrf and the obsolete get and post paths are gone', function (): void {
    $user = User::factory()->create();
    localIdentityCall(localIdentitySnapshot(), $user->id, csrf: false)->assertStatus(419);
    $this->get('https://restaurant-menu.test/local-login/'.$user->id)->assertNotFound();
    $this->post('https://restaurant-menu.test/local-login/'.$user->id)->assertNotFound();
    $this->assertGuest();
});

test('local account login rejects invalid or deleted users', function (mixed $identifier): void {
    $response = localIdentityCall(localIdentitySnapshot(), $identifier);
    if ($identifier === '999999999') {
        $response->assertNotFound();
    } else {
        $response->assertOk();
        $snapshot = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);
        expect($snapshot['memo']['errors'])->toHaveKey('form.userId');
    }
    $this->assertGuest();
})->with(['0', '999999999', 'not-a-user', '-1', true, [[1]]]);

test('an existing authenticated session cannot switch accounts using a stale local snapshot', function (): void {
    $current = User::factory()->create();
    $target = User::factory()->create();
    $snapshot = localIdentitySnapshot();
    $this->actingAs($current);
    localIdentityCall($snapshot, $target->id)->assertStatus(409);
    $this->assertAuthenticatedAs($current);
});

test('local account login is rate limited before resolving users', function (): void {
    $snapshot = localIdentitySnapshot();
    foreach (range(1, 20) as $attempt) {
        localIdentityCall($snapshot, '999999999')->assertNotFound();
    }
    localIdentityCall($snapshot, '999999999')->assertTooManyRequests();
    $this->assertGuest();
});

test('local account login has one Livewire entry and no duplicate native post route', function (): void {
    expect(Route::getRoutes()->getByName('local-login.authenticate'))->toBeNull();
    $snapshot = json_decode(localIdentitySnapshot(), true, flags: JSON_THROW_ON_ERROR);
    expect($snapshot['memo']['name'])->toBe('local.user-login')
        ->and($snapshot['data'])->not->toHaveKey('users');
});
