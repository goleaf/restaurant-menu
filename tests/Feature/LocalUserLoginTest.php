<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');
    config()->set([
        'app.env' => 'local',
        'demo-login.enabled' => true,
        'demo-login.allowed_hosts' => ['restaurant-menu.test'],
        'demo-login.password' => null,
    ]);
});

test('each local directory identity has its own csrf protected login button', function (): void {
    $users = User::factory()->count(2)->create();
    $response = $this->get('https://restaurant-menu.test/login')->assertOk();

    foreach ($users as $user) {
        $response->assertSee('action="'.route('local-login.authenticate', $user).'"', false)
            ->assertSee('data-test="local-login-user-'.$user->id.'"', false)
            ->assertSee(__('local_login.sign_in_as', ['name' => $user->name]));
    }

    $response->assertSee('name="_token"', false);
});

test('a local directory click logs in the selected ordinary account without a password or account changes', function (): void {
    $other = User::factory()->create();
    $selected = User::factory()->create();
    $original = $selected->fresh()->getRawOriginal();
    $this->withSession([
        '_token' => 'local-login-test-csrf',
        'auth.password_confirmed_at' => time(),
        'login.id' => $other->id,
        'login.remember' => true,
    ]);
    $previousSession = session()->getId();

    $this->post('https://restaurant-menu.test/local-login/'.$selected->id, [
        '_token' => 'local-login-test-csrf', 'email' => $other->email, 'remember' => true,
    ])->assertRedirect(route('dashboard'))
        ->assertSessionMissing('auth.password_confirmed_at')
        ->assertSessionMissing('login.id')
        ->assertSessionMissing('login.remember')
        ->assertHeaderContains('Cache-Control', 'no-store');

    $this->assertAuthenticatedAs($selected);
    expect(session()->getId())->not->toBe($previousSession)
        ->and($selected->refresh()->getRawOriginal())->toBe($original);
    $this->get(route('superadmin.dashboard'))->assertForbidden();
});

test('local account login is hidden before csrf outside the directory boundary', function (string $environment, string $configuredEnvironment, bool $enabled, string $host): void {
    $user = User::factory()->create();
    $this->app->detectEnvironment(fn (): string => $environment);
    config()->set(['app.env' => $configuredEnvironment, 'demo-login.enabled' => $enabled]);

    $this->post('https://'.$host.'/local-login/'.$user->id)->assertNotFound();
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

test('local account login rejects missing csrf and get requests', function (): void {
    $user = User::factory()->create();
    $url = 'https://restaurant-menu.test/local-login/'.$user->id;

    $this->post($url)->assertStatus(419);
    $this->get($url)->assertStatus(405);
    $this->assertGuest();
});

test('local account login rejects invalid or deleted users', function (string $identifier): void {
    $this->withSession(['_token' => 'local-login-test-csrf'])
        ->post('https://restaurant-menu.test/local-login/'.$identifier, ['_token' => 'local-login-test-csrf'])
        ->assertNotFound();
    $this->assertGuest();
})->with(['0', '999999999', 'not-a-user', '-1']);

test('an existing authenticated session cannot switch accounts through local login', function (): void {
    $current = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($current)->withSession(['_token' => 'local-login-test-csrf'])
        ->post('https://restaurant-menu.test/local-login/'.$target->id, ['_token' => 'local-login-test-csrf'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($current);
});

test('local account login is rate limited before resolving users', function (): void {
    $this->withSession(['_token' => 'local-login-test-csrf']);

    foreach (range(1, 20) as $attempt) {
        $this->post('https://restaurant-menu.test/local-login/999999999', ['_token' => 'local-login-test-csrf'])
            ->assertNotFound();
    }

    $this->post('https://restaurant-menu.test/local-login/999999999', ['_token' => 'local-login-test-csrf'])
        ->assertTooManyRequests();
    $this->assertGuest();
});

test('local account login keeps an explicit guest post route boundary', function (): void {
    $route = Route::getRoutes()->getByName('local-login.authenticate');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toBe(['POST'])
        ->and($route->gatherMiddleware())->toContain('web', 'local-login', 'guest', 'throttle:demo-login');
});
