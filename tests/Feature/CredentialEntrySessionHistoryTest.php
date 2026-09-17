<?php

declare(strict_types=1);

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

test('invitation exchange persists only its digest without writing the bearer into session history', function (): void {
    $token = str_repeat('I', 64);
    $invitation = Invitation::factory()->create(['invite_token_hash' => hash('sha256', $token)]);
    $users = User::query()->count();
    $memberships = OrganizationUser::query()->count();
    $this->get(route('login'))->assertOk();

    $this->get(route('invitations.show', ['token' => $token]))
        ->assertRedirect(route('invitations.pending'))
        ->assertSessionHas('staff_invitation_id', $invitation->id)
        ->assertSessionHas('staff_invitation_credential', hash('sha256', $token));

    $persisted = session()->getHandler()->read(session()->getId());
    expect($persisted)->toBeString()->not->toContain($token)
        ->and(session()->previousUrl())->toBe(route('login'))
        ->and(session()->get('_previous.route'))->toBe('login')
        ->and($invitation->refresh()->status)->toBe(InvitationStatus::Pending)
        ->and(User::query()->count())->toBe($users)
        ->and(OrganizationUser::query()->count())->toBe($memberships);
});

test('a validation redirect after credential entry cannot emit the bearer in its location or HTML', function (): void {
    $token = str_repeat('V', 64);
    Invitation::factory()->create(['invite_token_hash' => hash('sha256', $token)]);
    $this->get(route('login'))->assertOk();
    $this->get(route('invitations.show', ['token' => $token]))->assertRedirect(route('invitations.pending'));

    $response = $this->post(route('login.store'), [])->assertRedirect(route('login'));

    expect((string) $response->headers->get('Location'))->not->toContain($token)
        ->and($response->getContent())->not->toContain($token)
        ->and(session()->getHandler()->read(session()->getId()))->not->toContain($token);
});

test('speculative invitation entry preserves the previous safe URL and current attempt', function (string $method, array $headers): void {
    $token = str_repeat('S', 64);
    $safeUrl = route('login');
    $this->get($safeUrl)->assertOk();
    $this->withSession([
        'staff_invitation_id' => 987,
        'staff_invitation_credential' => 'preserved-digest',
        'staff_invitation_context' => 'preserved-context',
        'url.intended' => '/preserved',
    ])->call($method, route('invitations.show', ['token' => $token]), server: $this->transformHeadersToServerVars($headers))
        ->assertRedirect(route('invitations.pending'))
        ->assertSessionHas('staff_invitation_id', 987)
        ->assertSessionHas('staff_invitation_credential', 'preserved-digest')
        ->assertSessionHas('staff_invitation_context', 'preserved-context')
        ->assertSessionHas('url.intended', '/preserved');

    expect(session()->previousUrl())->toBe($safeUrl)
        ->and(session()->get('_previous.route'))->toBe('login')
        ->and(session()->getHandler()->read(session()->getId()))->not->toContain($token);
})->with([
    'head' => ['HEAD', []],
    'purpose' => ['GET', ['Purpose' => 'prefetch']],
    'sec-purpose' => ['GET', ['Sec-Purpose' => 'prefetch;prerender']],
    'mozilla-prefetch' => ['GET', ['X-Moz' => 'prefetch']],
]);

test('password reset entry retains its dedicated server credential without duplicating it into history', function (): void {
    $token = str_repeat('R', 64);
    $email = 'recipient@example.test';
    $this->get(route('login'))->assertOk();
    $this->get(route('password.reset', ['token' => $token, 'email' => $email]))
        ->assertRedirect(route('password.reset.form'))
        ->assertSessionHas('auth.livewire_password_reset.token', $token)
        ->assertSessionHas('auth.livewire_password_reset.email', $email);

    $context = session('auth.livewire_password_reset');
    $otherSessionState = session()->all();
    unset($otherSessionState['auth']['livewire_password_reset']);

    expect(session()->previousUrl())->toBe(route('login'))
        ->and(session()->get('_previous.route'))->toBe('login')
        ->and(serialize($otherSessionState))->not->toContain($token, $email)
        ->and(substr_count(session()->getHandler()->read(session()->getId()), $token))->toBe(1);

    $response = $this->post(route('login.store'), [])->assertRedirect(route('login'));
    expect($response->getContent())->not->toContain($token, $email)
        ->and(session('auth.livewire_password_reset'))->toBe($context);
    $this->get(route('password.reset.form'))->assertOk()->assertDontSee($token);
});

test('ordinary navigation still updates the previous URL and route', function (): void {
    $this->get(route('login'))->assertOk();
    $this->get(route('password.request'))->assertOk();

    expect(session()->previousUrl())->toBe(route('password.request'))
        ->and(session()->get('_previous.route'))->toBe('password.request');
});

test('session blocking retains its cache resolver and credential history boundary', function (string $routeName, bool $globalBlocking): void {
    $token = str_repeat('B', 64);
    Invitation::factory()->create(['invite_token_hash' => hash('sha256', $token)]);
    $this->get(route('password.request'))->assertOk();
    $sessionId = session()->getId();
    config(['session.block' => $globalBlocking, 'session.block_store' => 'array']);
    if (! $globalBlocking) {
        Route::getRoutes()->getByName($routeName)->block(10, 1);
    }

    $response = $this->get(route($routeName, $routeName === 'invitations.show' ? ['token' => $token] : []));

    if ($routeName === 'invitations.show') {
        $response->assertRedirect(route('invitations.pending'))
            ->assertSessionHas('staff_invitation_credential', hash('sha256', $token));
        expect(session()->previousUrl())->toBe(route('password.request'));
    } else {
        $response->assertOk();
        expect(session()->previousUrl())->toBe(route('login'));
    }

    expect(session()->getHandler()->read(session()->getId()))->not->toContain($token);
    $lock = Cache::store('array')->lock('session:'.$sessionId, 10);
    try {
        expect($lock->get())->toBeTrue();
    } finally {
        $lock->release();
    }
})->with(['login', 'invitations.show'])->with([
    'global blocking' => true,
    'route blocking' => false,
]);
