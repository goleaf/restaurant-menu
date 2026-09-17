<?php

declare(strict_types=1);

use App\Enums\InvitationStatus;
use App\Livewire\Invitations\Show;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Mail::fake();
    $this->token = str_repeat('A', 64);
    $this->invitation = Invitation::factory()->create([
        'email' => 'recipient@example.test',
        'invite_token_hash' => hash('sha256', $this->token),
    ]);
});

function invitationLivewireSnapshot(TestResponse $response): string
{
    test()->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    foreach ($matches[1] as $candidate) {
        $snapshot = html_entity_decode($candidate, ENT_QUOTES);
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === 'invitations.show') {
            return $snapshot;
        }
    }

    throw new RuntimeException('Invitation Livewire snapshot was not rendered.');
}

/** @param array<string, mixed> $updates */
function invitationLivewirePayload(string $snapshot, string $method, array $updates = []): array
{
    return ['components' => [[
        'snapshot' => $snapshot,
        'updates' => $updates,
        'calls' => [['method' => $method, 'params' => []]],
    ]]];
}

test('invitation exchange redirects to a genuine tokenless Livewire page without credential snapshots', function (): void {
    $this->get(route('invitations.show', ['token' => $this->token]))
        ->assertRedirect(route('invitations.pending'))
        ->assertDontSee($this->token);
    $response = $this->get(route('invitations.pending'))->assertOk()->assertSeeLivewire(Show::class);
    $snapshot = invitationLivewireSnapshot($response);

    expect($snapshot)->not->toContain($this->token, hash('sha256', $this->token), $this->invitation->credentialVersion())
        ->and(json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['path'])->toBe('invite/pending');
});

test('invitation speculative requests do not replace an existing credential context', function (string $method, array $headers): void {
    $this->withSession(['staff_invitation_id' => 987, 'staff_invitation_credential' => 'preserved', 'url.intended' => '/preserved'])
        ->call($method, route('invitations.show', ['token' => $this->token]), server: $this->transformHeadersToServerVars($headers))
        ->assertRedirect(route('invitations.pending'))
        ->assertSessionHas('staff_invitation_id', 987)
        ->assertSessionHas('staff_invitation_credential', 'preserved')
        ->assertSessionHas('url.intended', '/preserved');
})->with([
    'head' => ['HEAD', []],
    'purpose' => ['GET', ['Purpose' => 'prefetch']],
    'sec-purpose' => ['GET', ['Sec-Purpose' => 'prefetch;prerender']],
    'mozilla-prefetch' => ['GET', ['X-Moz' => 'prefetch']],
]);

test('signed invitation acceptance performs one authorized membership and rejects replay', function (): void {
    $recipient = User::factory()->create(['email' => $this->invitation->email]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $this->token]));
    $snapshot = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertOk());
    $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'accept'), ['X-Livewire' => ''])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', route('restaurant.dashboard'));

    expect($this->invitation->refresh()->status)->toBe(InvitationStatus::Accepted)
        ->and(OrganizationUser::query()->where('organization_id', $this->invitation->organization_id)->where('user_id', $recipient->id)->count())->toBe(1);
    $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'accept'), ['X-Livewire' => ''])->assertStatus(409);
    Mail::assertNothingSent();
});

test('an opened signed invitation rejects expired revoked rotated and changed consent', function (string $change): void {
    $recipient = User::factory()->create(['email' => $this->invitation->email]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $this->token]));
    $snapshot = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertOk());
    match ($change) {
        'expired' => $this->travel(8)->days(),
        'revoked' => $this->invitation->forceFill(['status' => InvitationStatus::Cancelled])->saveOrFail(),
        'rotated' => $this->invitation->forceFill(['invite_token_hash' => hash('sha256', str_repeat('B', 64))])->saveOrFail(),
        'changed' => $this->invitation->forceFill(['expires_at' => now()->addMonth()])->saveOrFail(),
    };

    $response = $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'accept'), ['X-Livewire' => ''])
        ->assertGone()->assertHeader('Referrer-Policy', 'no-referrer');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse();
})->with(['expired', 'revoked', 'rotated', 'changed']);

test('an old signed invitation cannot use a replaced browser session or account', function (string $change): void {
    $recipient = User::factory()->create(['email' => $this->invitation->email]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $this->token]));
    $snapshot = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertOk());
    if ($change === 'credential') {
        $this->get(route('invitations.show', ['token' => $this->token]))->assertRedirect();
    } elseif ($change === 'session') {
        session()->regenerate();
        $this->withCookie(config('session.cookie'), session()->getId());
    } else {
        $this->actingAs(User::factory()->create());
    }

    $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'accept'), ['X-Livewire' => ''])->assertStatus(409);
    expect($this->invitation->refresh()->status)->toBe(InvitationStatus::Pending)
        ->and(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse();
})->with(['credential', 'session', 'account']);

test('signed registration creates and logs in one recipient without serializing passwords or credentials', function (): void {
    $this->get(route('invitations.show', ['token' => $this->token]));
    $snapshot = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertOk());
    $session = session()->getId();
    Event::fake([Registered::class]);
    $response = $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'register', [
        'form.name' => ' New Recipient ', 'form.email' => ' RECIPIENT@EXAMPLE.TEST ',
        'form.password' => 'StrongPassword2026!', 'form.password_confirmation' => 'StrongPassword2026!',
    ]), ['X-Livewire' => ''])->assertOk()->assertJsonPath('components.0.effects.redirect', route('restaurant.dashboard'));

    $recipient = User::query()->where('email', 'recipient@example.test')->sole();
    $this->assertAuthenticatedAs($recipient);
    expect($recipient->name)->toBe('New Recipient')->and(session()->getId())->not->toBe($session)
        ->and(session()->has('staff_invitation_credential'))->toBeFalse()
        ->and($response->getContent())->not->toContain('StrongPassword2026!', $this->token, hash('sha256', $this->token));
    Event::assertDispatched(Registered::class, fn ($event): bool => $event->user->id === $recipient->id);
    Mail::assertNothingSent();
});

test('signed invitation validation preserves safe input and clears passwords even without an action', function (string $method): void {
    $this->get(route('invitations.show', ['token' => $this->token]));
    $snapshot = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertOk());
    $response = $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, $method, [
        'form.name' => '', 'form.email' => 'recipient@example.test',
        'form.password' => 'SensitiveShort', 'form.password_confirmation' => 'DifferentSecret',
    ]), ['X-Livewire' => ''])->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    $state = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR)['data']['form'][0];
    expect($state['password'])->toBe('')->and($state['password_confirmation'])->toBe('')
        ->and($state['email'])->toBe('recipient@example.test')
        ->and($response->getContent())->not->toContain('SensitiveShort', 'DifferentSecret', $this->token, hash('sha256', $this->token));
    if ($method === 'register') {
        expect($response->json('components.0.effects.html'))->toContain(__('invitations.validation.required', ['attribute' => __('ui.auth.register.full_name')]));
    }
    expect(User::query()->where('email', 'recipient@example.test')->exists())->toBeFalse();
})->with(['register', '$refresh']);

test('signed invitation registration validates hostile transport types before creating records', function (string $field, mixed $value): void {
    $this->get(route('invitations.show', ['token' => $this->token]));
    $snapshot = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertOk());
    $updates = ['form.name' => 'Recipient', 'form.email' => 'recipient@example.test', 'form.password' => 'StrongPassword2026!', 'form.password_confirmation' => 'StrongPassword2026!'];
    $updates['form.'.$field] = $value;
    $response = $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'register', $updates), ['X-Livewire' => ''])->assertOk();
    $snapshot = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);
    expect($snapshot['memo']['errors'])->toHaveKey('form.'.$field)
        ->and(User::query()->where('email', 'recipient@example.test')->exists())->toBeFalse();
})->with([['name', false], ['name', ['invalid']], ['email', ['invalid']], ['password', ['invalid']]]);

test('signed mismatch switch logs out and preserves the valid credential while invalidating the old tab', function (): void {
    $recipient = User::factory()->create(['email' => $this->invitation->email]);
    $this->actingAs(User::factory()->create())->get(route('invitations.show', ['token' => $this->token]));
    $snapshot = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertGone()->assertDontSee($recipient->email));
    $session = session()->getId();
    $csrf = session()->token();
    $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'switchAccount'), ['X-Livewire' => ''])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', route('login'));
    $this->assertGuest();
    expect(session()->getId())->not->toBe($session)->and(session()->token())->not->toBe($csrf)
        ->and(session('staff_invitation_credential'))->toBe(hash('sha256', $this->token));
    $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'switchAccount'), ['X-Livewire' => ''])->assertStatus(409);
    $this->actingAs($recipient);
    $fresh = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertOk());
    $this->postJson(route('default-livewire.update'), invitationLivewirePayload($fresh, 'accept'), ['X-Livewire' => ''])->assertOk();
    expect($this->invitation->refresh()->accepted_by_user_id)->toBe($recipient->id);
});

test('signed Livewire invitation actions retain the independent named limiter', function (): void {
    $this->get(route('invitations.show', ['token' => $this->token]));
    $snapshot = invitationLivewireSnapshot($this->get(route('invitations.pending'))->assertOk());
    foreach (range(1, 9) as $attempt) {
        $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'accept'), ['X-Livewire' => ''])->assertForbidden();
    }
    $this->postJson(route('default-livewire.update'), invitationLivewirePayload($snapshot, 'accept'), ['X-Livewire' => ''])->assertTooManyRequests();
});
