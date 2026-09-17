<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Tests\Support\IsolatedBrowserIdentity;
use Tests\Support\PasskeyFactory;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
});

test('two factor challenge retains focus validation and recovery login through real Livewire requests', function (): void {
    $this->enableFortifyFeatures([Features::twoFactorAuthentication(['confirm' => true])]);
    $user = User::factory()->withTwoFactor()->create(['password' => 'password']);
    $requests = authenticationMigrationRequests();
    $page = visit(route('login', absolute: false));
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('two-factor.login', absolute: false))
        ->assertPresent('[x-data="twoFactorChallenge"]')
        ->assertVisible('[x-ref="otp"] input >> nth=0')
        ->assertMissing('input[name="recovery_code"]');
    $page->assertScript('document.querySelector("[x-ref=otp]").contains(document.activeElement)');
    $page->fill('[x-ref="otp"] input >> nth=0', '1')
        ->assertAttribute('button[wire\\:click="toggleRecovery"]', 'type', 'button')
        ->keys('button[wire\\:click="toggleRecovery"]', 'Space')
        ->assertVisible('input[name="recovery_code"]')
        ->assertScript('document.activeElement.name', 'recovery_code')
        ->assertScript('Livewire.find(document.querySelector("[x-data=twoFactorChallenge]").getAttribute("wire:id")).get("form.code")', '');
    $page->fill('input[name="recovery_code"]', 'unfinished-recovery')
        ->click(__('ui.auth.two_factor_challenge.login_using_an_authentication_code'))
        ->assertVisible('[x-ref="otp"] input >> nth=0')
        ->assertMissing('input[name="recovery_code"]')
        ->assertScript('Livewire.find(document.querySelector("[x-data=twoFactorChallenge]").getAttribute("wire:id")).get("form.recovery_code")', '')
        ->assertScript('document.querySelector("[x-ref=otp]").contains(document.activeElement)');
    $page->click(__('ui.auth.two_factor_challenge.login_using_a_recovery_code'))
        ->assertVisible('input[name="recovery_code"]')
        ->fill('input[name="recovery_code"]', 'invalid-recovery-code')
        ->click('form[wire\\:submit="authenticate"] button[type="submit"]')
        ->assertPathIs(route('two-factor.login', absolute: false))
        ->assertSee(__('auth.two_factor_recovery_code'))
        ->assertVisible('input[name="recovery_code"]')
        ->assertMissing('[x-ref="otp"] input >> nth=0');
    expect($user->refresh()->recoveryCodes())->toBe(['recovery-code-1']);
    $page->fill('input[name="recovery_code"]', 'recovery-code-1')
        ->click('form[wire\\:submit="authenticate"] button[type="submit"]')
        ->assertPathIs(route('dashboard', absolute: false));
    expect($user->refresh()->recoveryCodes())->not->toContain('recovery-code-1');
    $page->click('@sidebar-menu-button')->click('@logout-button')
        ->assertPathIs(route('home', absolute: false))
        ->navigate(route('security.edit', absolute: false))
        ->assertPathIs(route('login', absolute: false))
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($requests->count('POST', route('login.store', absolute: false), 302))->toBe(0)
        ->and($requests->count('POST', route('two-factor.login.store', absolute: false), 302))->toBe(0)
        ->and($requests->count('POST', route('logout', absolute: false), 302))->toBe(0)
        ->and($requests->livewireUpdates())->toBeGreaterThanOrEqual(7);
});

test('passkey registration uses real options transport and recovers from WebAuthn cancellation failure and deletion', function (): void {
    $this->enableFortifyFeatures([Features::passkeys(['confirmPassword' => true])]);
    $user = User::factory()->create(['password' => 'password']);
    $requests = authenticationMigrationRequests();
    authenticationMigrationWebAuthnBoundary();
    $page = visit(route('login', absolute: false));
    $registration = '[x-data="passkeyRegistration"]';
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false))
        ->navigate(route('security.edit', absolute: false))
        ->assertPathIs(route('password.confirm', absolute: false))
        ->fill('password', 'password')->click('@confirm-password-button')
        ->assertPathIs(route('security.edit', absolute: false))
        ->assertPresent($registration);
    $page->assertScript('window.authWebAuthn.secureContext')
        ->assertScript('typeof PublicKeyCredential', 'function')
        ->assertScript('typeof navigator.credentials.create', 'function')
        ->click($registration.' button[x-on\\:click="showForm = true"]')
        ->assertScript('document.activeElement === document.querySelector("[x-ref=passkeyNameInput]")')
        ->fill('[x-ref="passkeyNameInput"]', 'Cancelled test device')
        ->click($registration.' button[x-on\\:click="register()"]')
        ->assertScript('window.authWebAuthn.calls.length', 1)
        ->assertEnabled($registration.' button[x-on\\:click="register()"]')
        ->assertScript('Alpine.$data(document.querySelector("[x-data=passkeyRegistration]")).error', null)
        ->assertValue('[x-ref="passkeyNameInput"]', 'Cancelled test device');
    $page->script('window.authWebAuthn.mode = "pending";');
    $page->click($registration.' button[x-on\\:click="register()"]')
        ->assertScript('window.authWebAuthn.calls.length', 2)
        ->assertScript('window.authWebAuthn.calls.every(call => call.operation === "create" && call.challengeBytes > 0)')
        ->assertDisabled($registration.' button[x-on\\:click="register()"]')
        ->click($registration.' button[x-on\\:click="cancel()"]')
        ->assertMissing('[x-ref="passkeyNameInput"]')
        ->assertScript('window.authWebAuthn.aborted', 1)
        ->assertScript('Alpine.$data(document.querySelector("[x-data=passkeyRegistration]")).loading', false);
    expect($requests->count('GET', route('passkey.registration-options', absolute: false), 200))->toBe(2)
        ->and($requests->count('POST', route('passkey.store', absolute: false)))->toBe(0)
        ->and(Passkey::query()->count())->toBe(0);
    $page->script('window.authWebAuthn.mode = "failure";');
    $page->click($registration.' button[x-on\\:click="showForm = true"]')
        ->assertValue('[x-ref="passkeyNameInput"]', '')
        ->fill('[x-ref="passkeyNameInput"]', 'Retry test device')
        ->click($registration.' button[x-on\\:click="register()"]')
        ->assertScript('Alpine.$data(document.querySelector("[x-data=passkeyRegistration]")).error', __('auth.passkey_failed'))
        ->assertSee(__('auth.passkey_failed'))
        ->assertVisible($registration.' p[role="alert"]')
        ->assertValue('[x-ref="passkeyNameInput"]', 'Retry test device')
        ->assertEnabled($registration.' button[x-on\\:click="register()"]');
    expect($requests->count('POST', route('passkey.store', absolute: false)))->toBe(0)
        ->and(Passkey::query()->count())->toBe(0);
    $own = PasskeyFactory::new()->for($user)->create(['name' => 'Owned disposable key']);
    $foreign = PasskeyFactory::new()->create(['name' => 'Different account key']);
    $page->refresh()->assertSee('Owned disposable key')->assertDontSee('Different account key')
        ->click('button[wire\\:click="confirmDelete('.$own->id.')"]')
        ->assertVisible('dialog[data-modal="delete-passkey-modal"]')
        ->click('button[wire\\:click="closeDeleteModal"]')
        ->assertMissing('dialog[data-modal="delete-passkey-modal"]');
    $this->assertModelExists($own);
    $page->click('button[wire\\:click="confirmDelete('.$own->id.')"]')
        ->click('button[wire\\:click="deletePasskey"]')
        ->assertMissing('dialog[data-modal="delete-passkey-modal"]')
        ->assertDontSee('Owned disposable key')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    $this->assertModelMissing($own);
    $this->assertModelExists($foreign);
    expect($requests->livewireUpdates())->toBeGreaterThanOrEqual(4);
});

function authenticationMigrationRequests(): object
{
    $observations = new class
    {
        /** @var list<array{method: string, path: string, status: int}> */
        public array $requests = [];

        public function count(string $method, string $path, ?int $status = null): int
        {
            return count(array_filter($this->requests, fn (array $request): bool => $request['method'] === $method && $request['path'] === $path && ($status === null || $request['status'] === $status)));
        }

        public function livewireUpdates(): int
        {
            return count(array_filter($this->requests, fn (array $request): bool => $request['method'] === 'POST' && str_contains($request['path'], '/livewire') && $request['status'] === 200));
        }
    };
    $seen = new WeakMap;
    Event::listen(ResponsePrepared::class, function (ResponsePrepared $event) use ($observations, $seen): void {
        $index = $seen[$event->request] ?? count($observations->requests);
        $seen[$event->request] = $index;
        $observations->requests[$index] = ['method' => $event->request->method(), 'path' => $event->request->getPathInfo(), 'status' => $event->response->getStatusCode()];
    });

    return $observations;
}

function authenticationMigrationWebAuthnBoundary(): void
{
    $injected = new WeakMap;
    Event::listen(ResponsePrepared::class, function (ResponsePrepared $event) use ($injected): void {
        $content = $event->response->getContent();
        if (isset($injected[$event->request]) || ! $event->request->isMethod('GET') || ! is_string($content) || ! str_contains($content, '<head>')) {
            return;
        }
        $injected[$event->request] = true;
        $boundary = <<<'HTML'
            <script>
            (() => {
                const state = window.authWebAuthn = {
                    mode: 'cancel', calls: [], aborted: 0,
                    secureContext: window.isSecureContext,
                    nativePublicKeyCredential: typeof window.PublicKeyCredential === 'function',
                };
                if (typeof window.PublicKeyCredential !== 'function') {
                    Object.defineProperty(window, 'PublicKeyCredential', { configurable: true, value: class PublicKeyCredential {} });
                }
                const perform = (operation, options) => {
                    state.calls.push({ operation, challengeBytes: options.publicKey.challenge.byteLength });
                    if (state.mode === 'cancel') return Promise.reject(new DOMException('Test-owned ceremony cancelled', 'NotAllowedError'));
                    if (state.mode === 'failure') return Promise.reject(new DOMException('Test-owned authenticator unavailable', 'NotReadableError'));
                    return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => {
                        state.aborted++;
                        reject(new DOMException('Test-owned ceremony aborted', 'AbortError'));
                    }, { once: true }));
                };
                Object.defineProperty(navigator, 'credentials', {
                    configurable: true,
                    value: { get: options => perform('get', options), create: options => perform('create', options) },
                });
            })();
            </script>
            HTML;
        $event->response->setContent(str_replace('<head>', '<head>'.$boundary, $content));
    });
}
