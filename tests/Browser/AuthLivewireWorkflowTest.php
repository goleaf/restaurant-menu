<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Pest\Browser\Api\PendingAwaitablePage;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
});

test('real Livewire login confirmation reset and logout preserve secrets and localized field errors', function (): void {
    Notification::fake();
    $original = 'AuthBrowserOriginal2026!';
    $replacement = 'AuthBrowserReplacement2026!';
    $incorrect = 'AuthBrowserIncorrect2026!';
    $user = User::factory()->create(['password' => $original]);
    $observations = authWorkflowObservations([$original, $replacement, $incorrect]);
    $page = visit(route('login', absolute: false));

    foreach (['en', 'lt', 'ru'] as $locale) {
        $page->navigate(route('login', ['lang' => $locale], false))
            ->assertSee(__('ui.auth.login.log_in'))
            ->assertAttribute('form[wire\\:submit="login"]', 'novalidate', '')
            ->assertMissing('form[action$="/login"]');
        authWorkflowResize($page, 320);
        $page->assertScript('document.documentElement.scrollWidth <= innerWidth');
    }
    $page->navigate(route('login', ['lang' => 'en'], false))
        ->fill('input[name="email"]', $user->email)
        ->fill('input[name="password"]', $incorrect)
        ->click('@login-button')
        ->assertSee(__('auth.failed'))
        ->assertValue('input[name="password"]', '')
        ->assertAttribute('input[name="email"]', 'aria-invalid', 'true');
    authWorkflowResize($page, 320);
    $page->screenshot(filename: 'auth-livewire-login-errors-320')
        ->fill('input[name="password"]', $original)
        ->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->navigate(route('security.edit', absolute: false))
        ->assertPathIs(route('password.confirm', absolute: false))
        ->fill('input[name="password"]', $incorrect)
        ->click('@confirm-password-button')
        ->assertSee(__('auth.password'))
        ->assertValue('input[name="password"]', '')
        ->fill('input[name="password"]', $original)
        ->click('@confirm-password-button')
        ->assertPathIs(route('security.edit', absolute: false))
        ->click('@sidebar-menu-button')->click('@logout-button')
        ->assertPathIs(route('home', absolute: false))
        ->navigate(route('password.request', absolute: false))
        ->fill('input[name="email"]', $user->email)
        ->click('@email-password-reset-link-button')
        ->assertSee(__('passwords.sent'));

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
    $token = Notification::sent($user, ResetPassword::class)->sole()->token;
    $observations->protect($token);
    $page->navigate(route('password.reset', ['token' => $token, 'email' => $user->email], false))
        ->assertPathIs(route('password.reset.form', absolute: false))
        ->assertMissing('input[name="token"]')
        ->assertValue('input[name="email"]', $user->email);
    $page->script('document.documentElement.classList.add("dark")');
    authWorkflowResize($page, 1440);
    $page->screenshot(filename: 'auth-livewire-reset-dark-1440')
        ->fill('input[name="password"]', $replacement)
        ->fill('input[name="password_confirmation"]', $incorrect)
        ->click('@reset-password-button')
        ->assertValue('input[name="password"]', '')
        ->assertValue('input[name="password_confirmation"]', '')
        ->assertAttribute('input[name="password"]', 'aria-invalid', 'true')
        ->fill('input[name="password"]', $replacement)
        ->fill('input[name="password_confirmation"]', $replacement)
        ->click('@reset-password-button')
        ->assertPathIs(route('login', absolute: false))
        ->assertSee(__('passwords.reset'))
        ->fill('input[name="email"]', $user->email)
        ->fill('input[name="password"]', $replacement)
        ->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->click('@sidebar-menu-button')->click('@logout-button')
        ->assertPathIs(route('home', absolute: false))
        ->navigate(route('security.edit', absolute: false))
        ->assertPathIs(route('login', absolute: false))
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    expect(Hash::check($replacement, $user->fresh()->password))->toBeTrue();
    expect($observations->nativePosts)->toBe([]);
    expect($observations->safe)->toBeTrue();
    foreach (['login', 'confirm', 'sendResetLink', 'resetPassword', 'logout'] as $operation) {
        expect($observations->calls)->toContain($operation);
    }
    expect($observations->statuses)->not->toContain(419, 422, 500);
});

test('real Livewire authenticator code uses the installed TOTP provider and clears the credential', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $password = 'AuthTotpPassword2026!';
    $user = User::factory()->withTwoFactor()->create(['password' => $password]);
    $observations = authWorkflowObservations([$password]);
    $page = visit(route('login', absolute: false));
    $page->fill('input[name="email"]', $user->email)
        ->fill('input[name="password"]', $password)
        ->click('@login-button')
        ->assertPathIs(route('two-factor.login', absolute: false))
        ->assertVisible('[x-ref="otp"] input >> nth=0');
    authWorkflowResize($page, 320);
    $page->screenshot(filename: 'auth-livewire-totp-320');
    $code = app(Google2FA::class)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($user->two_factor_secret));
    $observations->protect($code);
    $page->fill('[x-ref="otp"] input >> nth=0', $code)
        ->click('form[wire\\:submit="authenticate"] button[type="submit"]')
        ->assertPathIs(route('dashboard', absolute: false))
        ->click('@sidebar-menu-button')->click('@logout-button')
        ->assertPathIs(route('home', absolute: false))
        ->navigate(route('security.edit', absolute: false))
        ->assertPathIs(route('login', absolute: false))
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($observations->nativePosts)->toBe([]);
    expect($observations->calls)->toContain('login', 'authenticate', 'logout');
    expect($observations->safe)->toBeTrue();
});

test('real Livewire verification notice resends once and logs out through its own component', function (): void {
    $this->enableFortifyFeatures([Features::emailVerification()]);
    Notification::fake();
    $password = 'AuthVerificationPassword2026!';
    $user = User::factory()->unverified()->create(['password' => $password]);
    $observations = authWorkflowObservations([$password]);
    $page = visit(route('login', absolute: false));
    $page->fill('input[name="email"]', $user->email)
        ->fill('input[name="password"]', $password)
        ->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->navigate(route('verification.notice', absolute: false))
        ->assertSee(__('ui.auth.verify_email.please_verify_your_email_address_by_clicking_on_the_li'))
        ->click('form[wire\\:submit="resend"] button[type="submit"]')
        ->assertSee(__('ui.auth.verify_email.a_new_verification_link_has_been_sent_to_the_email_add'));
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    authWorkflowResize($page, 320);
    $page->assertScript('document.documentElement.scrollWidth <= innerWidth')
        ->screenshot(filename: 'auth-livewire-verification-320')
        ->click('@logout-button')
        ->assertPathIs(route('home', absolute: false))
        ->navigate(route('verification.notice', absolute: false))
        ->assertPathIs(route('login', absolute: false))
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($observations->nativePosts)->toBe([]);
    expect($observations->calls)->toContain('login', 'resend', 'logout');
    expect($observations->safe)->toBeTrue();
});

function authWorkflowResize(PendingAwaitablePage $page, int $width): void
{
    $page->resize($width, 1000);
    $page->script('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
}

/** @param list<string> $forbidden */
function authWorkflowObservations(array $forbidden): object
{
    $observations = new class($forbidden)
    {
        /** @var list<string> */
        public array $calls = [];

        /** @var list<string> */
        public array $nativePosts = [];

        /** @var list<int> */
        public array $statuses = [];

        public bool $safe = true;

        /** @param list<string> $forbidden */
        public function __construct(private array $forbidden) {}

        public function protect(string $value): void
        {
            $this->forbidden[] = $value;
        }

        public function inspect(string $snapshot, string $response): void
        {
            foreach ($this->forbidden as $value) {
                $this->safe = $this->safe && ! str_contains($snapshot, $value) && ! str_contains($response, $value);
            }
        }
    };
    $seen = new WeakMap;
    Event::listen(ResponsePrepared::class, function (ResponsePrepared $event) use ($observations, $seen): void {
        if (isset($seen[$event->request])) {
            return;
        }
        $seen[$event->request] = true;
        if ($event->request->isMethod('POST') && $event->request->routeIs('login.store', 'logout', 'password.email', 'password.update', 'password.confirm.store', 'two-factor.login.store', 'verification.send')) {
            $observations->nativePosts[] = (string) $event->request->route()?->getName();
        }
        foreach ($event->request->input('components', []) as $component) {
            $snapshot = (string) ($component['snapshot'] ?? '');
            $decoded = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
            if (! str_starts_with($decoded['memo']['name'] ?? '', 'auth.')) {
                continue;
            }
            foreach ($component['calls'] ?? [] as $call) {
                $observations->calls[] = (string) ($call['method'] ?? '');
            }
            $observations->statuses[] = $event->response->getStatusCode();
            $observations->inspect($snapshot, (string) $event->response->getContent());
        }
    });

    return $observations;
}
