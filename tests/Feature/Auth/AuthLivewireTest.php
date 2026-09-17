<?php

use App\Models\User;
use App\Support\Auth\AuthRequestAdapter;
use App\Support\Auth\LivewireAuthRedirectResponse;
use Illuminate\Auth\Notifications\ResetPassword as ResetNotification;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

function authPageSnapshot(string $route, string $component): string
{
    $response = test()->get(route($route))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5);
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component) {
            return $snapshot;
        }
    }
    throw new RuntimeException('Missing authentication component snapshot.');
}

/** @param array<string, mixed> $updates */
function authLivewireCall(string $snapshot, string $method, array $updates = []): TestResponse
{
    return test()->withCredentials()->withCookie(config('session.cookie'), session()->getId())
        ->postJson(route('default-livewire.update'), ['components' => [[
            'snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['method' => $method, 'params' => []]],
        ]]], ['X-Livewire' => '', 'X-CSRF-TOKEN' => session()->token()]);
}

/** @return array<string, mixed> */
function authResponseSnapshot(TestResponse $response): array
{
    return json_decode($response->assertOk()->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);
}

function authResetSnapshot(string $token, string $email): string
{
    test()->get(route('password.reset', ['token' => $token, 'email' => $email]))->assertRedirect(route('password.reset.form'));

    return authPageSnapshot('password.reset.form', 'auth.reset-password');
}

afterEach(function (): void {
    Fortify::$authenticateThroughCallback = null;
    Fortify::$authenticateUsingCallback = null;
});

test('signed livewire login authenticates through fortify and erases credentials', function (): void {
    $user = User::factory()->create();
    $snapshot = authPageSnapshot('login', 'auth.login');
    session()->put('url.intended', '/settings/profile');
    $sessionId = session()->getId();
    $response = authLivewireCall($snapshot, 'login', [
        'form.email' => strtoupper($user->email), 'form.password' => 'password', 'form.remember' => true,
    ])->assertOk()->assertJsonPath('components.0.effects.redirect', url('/settings/profile'));
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionId);
    expect(authResponseSnapshot($response)['data']['form'][0]['password'])->toBe('');
    expect($user->fresh()->remember_token)->not->toBeNull();
    expect($response->getContent())->not->toContain('"password":"password"');
});

test('signed livewire login shares the named limiter with fortify', function (): void {
    $user = User::factory()->create();
    $snapshot = authPageSnapshot('login', 'auth.login');
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
    }
    $response = authLivewireCall($snapshot, 'login', ['form.email' => $user->email, 'form.password' => 'incorrect']);
    $state = authResponseSnapshot($response);
    expect($state['memo']['errors'])->toHaveKey('form.email');
    expect($state['data']['form'][0]['password'])->toBe('');
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
    $this->assertGuest();
});

test('signed livewire login preserves the configured pipeline callback', function (): void {
    $user = User::factory()->create();
    $called = false;
    Fortify::authenticateThrough(function (LoginRequest $request) use (&$called, $user): array {
        $called = $request->input('email') === $user->email && $request->input('password') === 'password';

        return [AttemptToAuthenticate::class, PrepareAuthenticatedSession::class];
    });
    authLivewireCall(authPageSnapshot('login', 'auth.login'), 'login', [
        'form.email' => $user->email, 'form.password' => 'password',
    ])->assertOk()->assertJsonPath('components.0.effects.redirect', url('/dashboard'));
    expect($called)->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('the fortify request adapter does not mutate global request input or headers', function (): void {
    $source = Request::create('/livewire/update?original=yes', 'POST', ['components' => ['existing']]);
    $source->headers->set('Accept', 'application/json');
    $source->setLaravelSession(session()->driver());
    $scoped = app(AuthRequestAdapter::class)->request($source, ['email' => 'local@example.test', 'password' => 'transient'], LoginRequest::class);
    expect($scoped)->toBeInstanceOf(LoginRequest::class);
    expect($scoped->input('password'))->toBe('transient');
    expect($source->all())->toBe(['components' => ['existing'], 'original' => 'yes']);
    expect($source->headers->get('Accept'))->toBe('application/json');
    expect($scoped->session())->toBe($source->session());
});

test('auth passwords are cleared on refresh and invalid raw form values', function (string $method, mixed $email): void {
    $response = authLivewireCall(authPageSnapshot('login', 'auth.login'), $method, [
        'form.email' => $email, 'form.password' => 'must-not-survive',
    ]);
    $snapshot = authResponseSnapshot($response);
    expect($snapshot['data']['form'][0]['password'])->toBe('');
    expect($response->getContent())->not->toContain('must-not-survive');
    if ($method === 'login') {
        expect($snapshot['memo']['errors'])->toHaveKey('form.email');
    }
})->with([['$refresh', 'valid@example.test'], ['login', ['invalid']], ['login', true]]);

test('signed livewire mfa consumes a pending recovery code once', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $code = $user->recoveryCodes()[0];
    authLivewireCall(authPageSnapshot('login', 'auth.login'), 'login', [
        'form.email' => $user->email, 'form.password' => 'password', 'form.remember' => true,
    ])->assertOk()->assertJsonPath('components.0.effects.redirect', route('two-factor.login'));
    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
    $response = authLivewireCall(authPageSnapshot('two-factor.login', 'auth.two-factor-challenge'), 'authenticate', ['form.recovery_code' => $code])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', url('/dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(session()->has('login.id'))->toBeFalse();
    expect(session()->has('login.remember'))->toBeFalse();
    expect($user->fresh()->recoveryCodes())->not->toContain($code);
    expect(authResponseSnapshot($response)['data']['form'][0]['recovery_code'])->toBe('');
    Auth::logout();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $response = authLivewireCall(authPageSnapshot('two-factor.login', 'auth.two-factor-challenge'), 'authenticate', ['form.recovery_code' => $code]);
    expect(authResponseSnapshot($response)['memo']['errors'])->toHaveKey('form.recovery_code');
    expect($response->getContent())->not->toContain($code);
    $this->assertGuest();
});

test('signed livewire mfa uses the provider and rejects absent pending identity', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication()]);
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
    $user = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $this->mock(TwoFactorAuthenticationProvider::class)->shouldReceive('verify')->once()->withArgs(
        fn (string $secret, string $code): bool => $secret !== '' && $code === '123456',
    )->andReturnTrue();
    $response = authLivewireCall(authPageSnapshot('two-factor.login', 'auth.two-factor-challenge'), 'authenticate', ['form.code' => '123456'])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', url('/dashboard'));
    expect(authResponseSnapshot($response)['data']['form'][0]['code'])->toBe('');
    $this->assertAuthenticatedAs($user);
});

test('the two factor factory state works with the real installed authenticator provider', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $provider = app(TwoFactorAuthenticationProvider::class);
    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();
    expect($provider->verify($secret, $code))->toBeTrue();
    expect($provider->verify($secret, 'invalid'))->toBeFalse();
    expect($user->toArray())->not->toHaveKey('two_factor_secret');
});

test('signed livewire forgot password does not disclose existing or throttled accounts', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $statuses = [];
    foreach ([$user->email, 'missing@example.test', $user->email] as $email) {
        $response = authLivewireCall(authPageSnapshot('password.request', 'auth.forgot-password'), 'sendResetLink', ['form.email' => $email]);
        $statuses[] = authResponseSnapshot($response)['data']['status'];
    }
    expect(array_unique($statuses))->toBe([__('passwords.sent')]);
    Notification::assertSentToTimes($user, ResetNotification::class, 1);
});

test('signed livewire resets a password with a session-only credential and rejects replay', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $snapshot = authResetSnapshot($token, $user->email);
    expect($snapshot)->not->toContain($token);
    $response = authLivewireCall($snapshot, 'resetPassword', [
        'form.password' => 'new-secure-password', 'form.password_confirmation' => 'new-secure-password',
    ])->assertOk()->assertJsonPath('components.0.effects.redirect', route('login'));
    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
    expect($response->getContent())->not->toContain($token, 'new-secure-password');
    expect(session('auth.livewire_password_reset'))->toBeNull();
    $response = authLivewireCall(authResetSnapshot($token, $user->email), 'resetPassword', [
        'form.password' => 'other-secure-password', 'form.password_confirmation' => 'other-secure-password',
    ]);
    expect(authResponseSnapshot($response)['memo']['errors'])->toHaveKey('form.email');
    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

test('a second reset context cannot replace the credential behind an already mounted form', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $snapshot = authResetSnapshot(Password::broker()->createToken($first), $first->email);
    authResetSnapshot(Password::broker()->createToken($second), $second->email);
    $response = authLivewireCall($snapshot, 'resetPassword', [
        'form.email' => $second->email, 'form.password' => 'new-secure-password', 'form.password_confirmation' => 'new-secure-password',
    ]);
    expect(authResponseSnapshot($response)['memo']['errors'])->toHaveKey('form.email');
    expect(Hash::check('password', $first->fresh()->password))->toBeTrue();
    expect(Hash::check('password', $second->fresh()->password))->toBeTrue();
});

test('signed livewire password confirmation clears failed and valid input', function (): void {
    $this->get(route('password.confirm'))->assertRedirect(route('login'));
    session()->forget('url.intended');
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = authLivewireCall(authPageSnapshot('password.confirm', 'auth.confirm-password'), 'confirm', ['form.password' => 'incorrect']);
    $snapshot = authResponseSnapshot($response);
    expect($snapshot['memo']['errors'])->toHaveKey('form.password');
    expect($snapshot['data']['form'][0]['password'])->toBe('');
    expect(session()->has('auth.password_confirmed_at'))->toBeFalse();
    $response = authLivewireCall(authPageSnapshot('password.confirm', 'auth.confirm-password'), 'confirm', ['form.password' => 'password'])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', url('/dashboard'));
    expect(authResponseSnapshot($response)['data']['form'][0]['password'])->toBe('');
    expect(session('auth.password_confirmed_at'))->toBe(now()->timestamp);
});

test('failed livewire password confirmation retains the original protected destination for retry', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    expect(session('url.intended'))->toBe(route('security.edit'));
    $snapshot = authPageSnapshot('password.confirm', 'auth.confirm-password');
    $failure = authLivewireCall($snapshot, 'confirm', ['form.password' => 'incorrect'])->assertOk();
    expect(session('url.intended'))->toBe(route('security.edit'));
    authLivewireCall($failure->json('components.0.snapshot'), 'confirm', ['form.password' => 'password'])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', route('security.edit'));
});

test('signed verification and logout preserve authorization and session lifecycle', function (): void {
    Notification::fake();
    $this->enableFortifyFeatures([Features::emailVerification()]);
    $this->get(route('verification.notice'))->assertRedirect(route('login'));
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);
    $response = authLivewireCall(authPageSnapshot('verification.notice', 'auth.verify-email'), 'resend');
    expect(authResponseSnapshot($response)['data']['verificationLinkSent'])->toBeTrue();
    Notification::assertSentTo($user, VerifyNotification::class);
    $snapshot = authPageSnapshot('verification.notice', 'auth.logout');
    session()->put(['auth.password_confirmed_at' => now()->timestamp, 'login.id' => 999]);
    $sessionId = session()->getId();
    $csrf = session()->token();
    authLivewireCall($snapshot, 'logout')->assertOk()->assertJsonPath('components.0.effects.redirect', route('home'));
    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionId);
    expect(session()->token())->not->toBe($csrf);
    expect(session()->has('auth.password_confirmed_at'))->toBeFalse();
    expect(session()->has('login.id'))->toBeFalse();
});

test('a signed auth snapshot from before an account change cannot act as the new account', function (): void {
    $snapshot = authPageSnapshot('login', 'auth.login');
    $user = User::factory()->create();
    $this->actingAs($user);
    authLivewireCall($snapshot, 'login', ['form.email' => $user->email, 'form.password' => 'password'])->assertStatus(409);
    $this->assertAuthenticatedAs($user);
});

test('signed auth protects refresh credentials for reset confirmation and mfa', function (string $page): void {
    $fields = ['form.password' => 'transient-secret'];
    if ($page === 'reset') {
        $user = User::factory()->create();
        $snapshot = authResetSnapshot(Password::broker()->createToken($user), $user->email);
        $fields['form.password_confirmation'] = 'transient-secret';
    } elseif ($page === 'confirm') {
        $this->actingAs(User::factory()->create());
        $snapshot = authPageSnapshot('password.confirm', 'auth.confirm-password');
    } else {
        $this->enableFortifyFeatures([Features::twoFactorAuthentication()]);
        $user = User::factory()->withTwoFactor()->create();
        $this->get(route('login'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
        $snapshot = authPageSnapshot('two-factor.login', 'auth.two-factor-challenge');
        $fields = ['form.code' => '123456', 'form.recovery_code' => 'transient-secret'];
    }
    $response = authLivewireCall($snapshot, '$refresh', $fields);
    $form = authResponseSnapshot($response)['data']['form'][0];
    foreach (array_keys($fields) as $field) {
        expect($form[substr($field, 5)])->toBe('');
    }
    expect($response->getContent())->not->toContain('transient-secret');
})->with(['reset', 'confirm', 'mfa']);

test('signed reset validates password confirmation before broker mutation', function (): void {
    $user = User::factory()->create();
    $snapshot = authResetSnapshot(Password::broker()->createToken($user), $user->email);
    $response = authLivewireCall($snapshot, 'resetPassword', [
        'form.password' => 'new-secure-password', 'form.password_confirmation' => 'different-password',
    ]);
    expect(authResponseSnapshot($response)['memo']['errors'])->toHaveKey('form.password');
    expect($response->getContent())->not->toContain('new-secure-password', 'different-password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('signed reset rejects expired session credentials without replacing the old password', function (): void {
    $user = User::factory()->create();
    $snapshot = authResetSnapshot(Password::broker()->createToken($user), $user->email);
    $this->travel(61)->minutes();
    $response = authLivewireCall($snapshot, 'resetPassword', [
        'form.password' => 'new-secure-password', 'form.password_confirmation' => 'new-secure-password',
    ]);
    expect(authResponseSnapshot($response)['memo']['errors'])->toHaveKey('form.email');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    expect(session('auth.livewire_password_reset'))->toBeNull();
});

test('reset exchange rejects malformed and prefetched credentials without storing them', function (): void {
    $this->get(route('password.reset', ['token' => 'untrusted', 'email' => ['invalid']]))->assertNotFound();
    $this->get(route('password.reset', ['token' => 'untrusted', 'email' => 'invalid']))->assertNotFound();
    $this->get(route('password.reset', ['token' => 'untrusted', 'email' => 'user@example.test']), ['Purpose' => 'prefetch'])->assertNoContent();
    $this->head(route('password.reset', ['token' => 'untrusted', 'email' => 'user@example.test']))->assertNoContent();
    expect(session('auth.livewire_password_reset'))->toBeNull();
});

test('configured login pipeline and custom authentication callback remain authoritative', function (): void {
    $user = User::factory()->create();
    config(['fortify.pipelines.login' => [AttemptToAuthenticate::class, PrepareAuthenticatedSession::class]]);
    Fortify::authenticateUsing(fn (Request $request): ?User => $request->input('password') === 'custom-secret' ? $user : null);
    authLivewireCall(authPageSnapshot('login', 'auth.login'), 'login', ['form.email' => $user->email, 'form.password' => 'custom-secret'])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', url('/dashboard'))
        ->assertJsonMissingPath('components.0.effects.redirectUsingNavigate');
    $this->assertAuthenticatedAs($user);
});

test('unconfirmed mfa follows the configured fortify confirmation flag', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create(['two_factor_confirmed_at' => null]);
    authLivewireCall(authPageSnapshot('login', 'auth.login'), 'login', ['form.email' => $user->email, 'form.password' => 'password'])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', url('/dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(session()->has('login.id'))->toBeFalse();
});

test('verification resend preserves rate limiting and does not resend to a verified account', function (): void {
    Notification::fake();
    $this->enableFortifyFeatures([Features::emailVerification()]);
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);
    $snapshot = authPageSnapshot('verification.notice', 'auth.verify-email');
    for ($attempt = 0; $attempt < 6; $attempt++) {
        authLivewireCall($snapshot, 'resend')->assertOk();
    }
    $response = authLivewireCall($snapshot, 'resend');
    expect(authResponseSnapshot($response)['memo']['errors'])->toHaveKey('verificationLinkSent');
    Notification::assertSentToTimes($user, VerifyNotification::class, 6);
    $user->markEmailAsVerified();
    $this->get(route('verification.notice'))->assertRedirect(url('/dashboard'));
});

test('disabled optional auth features reject a previously signed component action', function (string $feature): void {
    if ($feature === 'verification') {
        $this->enableFortifyFeatures([Features::emailVerification()]);
        $this->actingAs(User::factory()->unverified()->create());
        $snapshot = authPageSnapshot('verification.notice', 'auth.verify-email');
        $method = 'resend';
        $updates = [];
    } else {
        $snapshot = authPageSnapshot('password.request', 'auth.forgot-password');
        $method = 'sendResetLink';
        $updates = ['form.email' => 'user@example.test'];
    }
    config(['fortify.features' => []]);
    authLivewireCall($snapshot, $method, $updates)->assertNotFound();
})->with(['verification', 'reset']);

test('the livewire login limiter returns a localized form error without echoing the password', function (): void {
    $user = User::factory()->create();
    $snapshot = authPageSnapshot('login', 'auth.login');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
    }
    $response = authLivewireCall($snapshot, 'login', ['form.email' => $user->email, 'form.password' => 'transient-secret']);
    $state = authResponseSnapshot($response);
    expect($state['memo']['errors'])->toHaveKey('form.email');
    expect($state['data']['form'][0]['password'])->toBe('');
    expect($response->getContent())->not->toContain('transient-secret');
    $this->assertGuest();
});

test('an expired mfa login cannot authenticate a previously mounted challenge', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication()]);
    $user = User::factory()->withTwoFactor()->create();
    $this->get(route('login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $snapshot = authPageSnapshot('two-factor.login', 'auth.two-factor-challenge');
    session()->forget('login.id');
    session()->save();
    authLivewireCall($snapshot, 'authenticate', ['form.recovery_code' => $user->recoveryCodes()[0]])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', route('login'));
    $this->assertGuest();
});

test('custom fortify login responses keep their redirect target', function (): void {
    $user = User::factory()->create();
    $this->app->bind(LoginResponse::class, fn () => new class implements LoginResponse
    {
        public function toResponse($request): Response
        {
            return new RedirectResponse(url('/settings/profile'));
        }
    });
    authLivewireCall(authPageSnapshot('login', 'auth.login'), 'login', ['form.email' => $user->email, 'form.password' => 'password'])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', url('/settings/profile'))
        ->assertJsonMissingPath('components.0.effects.redirectUsingNavigate');
    $this->assertAuthenticatedAs($user);
});

test('an ordinary no-content response is not treated as an already recorded Livewire redirect', function (): void {
    $response = new Response('', 204);
    $adapted = app(AuthRequestAdapter::class)->response($response, Request::create('/login'));
    expect($adapted)->toBe($response)->not->toBeInstanceOf(LivewireAuthRedirectResponse::class);
});

test('both mfa transports expire a successful first factor after ten minutes', function (string $transport): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication()]);
    $user = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $snapshot = authPageSnapshot('two-factor.login', 'auth.two-factor-challenge');
    $this->travel(11)->minutes();
    if ($transport === 'livewire') {
        authLivewireCall($snapshot, 'authenticate', ['form.recovery_code' => $user->recoveryCodes()[0]])
            ->assertOk()->assertJsonPath('components.0.effects.redirect', route('login'));
    } else {
        $this->post(route('two-factor.login.store'), ['recovery_code' => $user->recoveryCodes()[0]])->assertRedirect(route('login'));
    }
    $this->assertGuest();
    expect($user->fresh()->recoveryCodes())->toContain('recovery-code-1');
    expect(session()->has('login.id'))->toBeFalse();
})->with(['livewire', 'fortify']);

test('a second first factor invalidates the previously mounted mfa challenge', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication()]);
    $user = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $snapshot = authPageSnapshot('two-factor.login', 'auth.two-factor-challenge');
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    authLivewireCall($snapshot, 'authenticate', ['form.recovery_code' => $user->recoveryCodes()[0]])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', route('login'));
    $this->assertGuest();
    expect($user->fresh()->recoveryCodes())->toContain('recovery-code-1');
});

test('mfa limiter is shared by the livewire and native fortify transports', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication()]);
    $user = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $snapshot = authPageSnapshot('two-factor.login', 'auth.two-factor-challenge');
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'invalid'])->assertSessionHasErrors('recovery_code');
    }
    $response = authLivewireCall($snapshot, 'authenticate', ['form.recovery_code' => 'invalid']);
    expect(authResponseSnapshot($response)['memo']['errors'])->toHaveKey('form.recovery_code');
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertTooManyRequests();
    $this->assertGuest();
});

test('consumed mfa challenge receipts reject a lost-response replay with another valid recovery code', function (string $transport): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication()]);
    $user = User::factory()->withTwoFactor()->create([
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['first-recovery', 'second-recovery'])),
    ]);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $context = session('auth.livewire_two_factor_challenge');
    if ($transport === 'livewire') {
        authLivewireCall(authPageSnapshot('two-factor.login', 'auth.two-factor-challenge'), 'authenticate', ['form.recovery_code' => 'first-recovery'])
            ->assertOk()->assertJsonPath('components.0.effects.redirect', url('/dashboard'));
    } else {
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'first-recovery'])->assertRedirect(url('/dashboard'));
    }
    $this->assertAuthenticatedAs($user);
    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->withSession(['auth.livewire_two_factor_challenge' => $context, 'login.id' => $user->id]);
    session()->save();
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'second-recovery'])->assertRedirect(route('login'));
    $this->assertGuest();
    expect($user->fresh()->recoveryCodes())->toContain('second-recovery')->not->toContain('first-recovery');
})->with(['livewire', 'fortify']);

test('a simultaneous mfa operation cannot acquire the same user lock or consume a recovery code', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication()]);
    $user = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $snapshot = authPageSnapshot('two-factor.login', 'auth.two-factor-challenge');
    $lock = Cache::store('file')->lock('auth-mfa-user:'.hash('sha256', (string) $user->id), 30);
    expect($lock->get())->toBeTrue();
    try {
        $response = authLivewireCall($snapshot, 'authenticate', ['form.recovery_code' => 'recovery-code-1']);
        expect(authResponseSnapshot($response)['memo']['errors'])->toHaveKey('form.recovery_code');
        $this->assertGuest();
        expect($user->fresh()->recoveryCodes())->toContain('recovery-code-1');
    } finally {
        $lock->release();
    }
    authLivewireCall($snapshot, 'authenticate', ['form.recovery_code' => 'recovery-code-1'])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', url('/dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('pending mfa challenges reject revoked or replaced credentials in both transports', function (string $transport, string $change): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $snapshot = authPageSnapshot('two-factor.login', 'auth.two-factor-challenge');
    $updated = match ($change) {
        'disabled' => ['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null],
        'replaced' => ['two_factor_secret' => Fortify::currentEncrypter()->encrypt('ANOTHERTESTSECRET'), 'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['replacement-recovery']))],
        'unconfirmed' => ['two_factor_confirmed_at' => null],
    };
    $user->forceFill($updated)->save();
    $currentCodes = $user->two_factor_recovery_codes;
    $code = $change === 'replaced' ? 'replacement-recovery' : 'recovery-code-1';
    if ($transport === 'livewire') {
        $response = authLivewireCall($snapshot, 'authenticate', ['form.recovery_code' => $code])
            ->assertOk()->assertJsonPath('components.0.effects.redirect', route('login'));
        expect($response->getContent())->not->toContain($code, 'credential_fingerprint');
        if (is_string($user->two_factor_secret)) {
            expect($response->getContent())->not->toContain($user->two_factor_secret);
        }
    } else {
        $this->post(route('two-factor.login.store'), ['recovery_code' => $code])->assertRedirect(route('login'));
    }
    $this->assertGuest();
    expect($user->fresh()->two_factor_recovery_codes)->toBe($currentCodes);
    expect(session()->has('login.id'))->toBeFalse();
    expect(session()->has('auth.livewire_two_factor_challenge'))->toBeFalse();
})->with(['livewire', 'fortify'])->with(['disabled', 'replaced', 'unconfirmed']);
