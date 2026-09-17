<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Livewire\Local\DemoLogin;
use App\Livewire\Local\UserLogin;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');
    config()->set(['app.env' => 'local', 'demo-login.enabled' => true, 'demo-login.allowed_hosts' => ['localhost', '127.0.0.1', 'www.example.com'], 'demo-login.password' => null]);
});

test('local Livewire login preserves the selected account and clears previous confirmation state', function (): void {
    $other = User::factory()->create();
    $target = User::factory()->create();
    $original = $target->fresh()->getRawOriginal();
    session()->put(['auth.password_confirmed_at' => time(), 'login.id' => $other->id, 'login.remember' => true]);
    $previous = session()->getId();

    identityMigrationCall('/login', 'local.user-login', $target->id)->assertOk()->assertJsonPath('components.0.effects.redirect', route('dashboard'));

    $this->assertAuthenticatedAs($target);
    expect(session()->getId())->not->toBe($previous)
        ->and(session()->has('auth.password_confirmed_at'))->toBeFalse()
        ->and(session()->has('login.id'))->toBeFalse()
        ->and(session()->has('login.remember'))->toBeFalse()
        ->and($target->fresh()->getRawOriginal())->toBe($original);
});

test('local login rejects malformed values without coercing another identity', function (mixed $value): void {
    User::factory()->create();
    Livewire::test(UserLogin::class)->call('login', $value)->assertHasErrors('form.userId');
    $this->assertGuest();
})->with([true, false, 'not-an-id', 0, -1, [[['id' => 1]]]]);

test('a mounted local directory rechecks the environment before every action', function (): void {
    $target = User::factory()->create();
    $component = Livewire::test(UserLogin::class);
    config()->set('demo-login.enabled', false);
    $component->call('login', $target->id)->assertNotFound();
    $this->assertGuest();
});

test('local directory is absent in production even if explicitly mounted', function (): void {
    $target = User::factory()->create();
    $this->app->detectEnvironment(fn (): string => 'production');
    config()->set('app.env', 'production');
    Livewire::test(UserLogin::class)->assertDontSee($target->email)->assertDontSee($target->name)
        ->call('login', $target->id)->assertNotFound();
    $this->assertGuest();
});

test('local identity actions cannot replace an authenticated account', function (): void {
    $current = User::factory()->create();
    $target = User::factory()->create();
    Livewire::actingAs($current)->test(UserLogin::class)->call('login', $target->id)->assertForbidden();
    $this->assertAuthenticatedAs($current);
});

test('local action attempts use the named demo limit before identity lookup', function (): void {
    $component = Livewire::test(UserLogin::class);
    foreach (range(1, 20) as $attempt) {
        $component->call('login', 'invalid')->assertHasErrors('form.userId');
    }
    $component->call('login', 'invalid')->assertStatus(429);
    $this->assertGuest();
});

test('demo Livewire login only resolves the fixed role identity', function (): void {
    $identity = DemoAccountCatalog::forRole(SystemRole::Waiter);
    $user = User::factory()->demoIdentity($identity['name'], $identity['email'])->create();
    $user->roles()->attach(Role::factory()->create(['code' => SystemRole::Waiter])->id);
    identityMigrationCall('/demo-login', 'local.demo-login', SystemRole::Waiter->value)->assertOk()->assertJsonPath('components.0.effects.redirect', route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('demo Livewire rejects an unknown role and never exposes a password', function (): void {
    Livewire::test(DemoLogin::class)->assertDontSee('type="password"', false)
        ->call('login', 'arbitrary-class')->assertHasErrors('form.role');
    $this->assertGuest();
});

function identityMigrationCall(string $url, string $component, mixed $target): TestResponse
{
    $response = test()->get($url)->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5);
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component) {
            return test()->withCredentials()->withCookie(config('session.cookie'), session()->getId())->postJson(route('default-livewire.update'), [
                'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => 'login', 'params' => [$target]]]]],
            ], ['X-Livewire' => '', 'X-CSRF-TOKEN' => session()->token()]);
        }
    }
    throw new RuntimeException('Missing identity component.');
}
