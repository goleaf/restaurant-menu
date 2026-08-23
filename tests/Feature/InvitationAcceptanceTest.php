<?php

declare(strict_types=1);

use App\Actions\Invitations\CreatedInvitation;
use App\Actions\Invitations\CreateInvitationAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('new invitation credentials are returned once and only digests are persisted', function (): void {
    $organization = Organization::factory()->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $invitedBy = User::factory()->create();

    $createdInvitation = app(CreateInvitationAction::class)->handle(
        $organization,
        $role,
        $invitedBy,
        ['invite_token' => str_repeat('T', 64), 'invite_code' => 'WAITER01'],
    );

    expect($createdInvitation->token)->toBe(str_repeat('T', 64))
        ->and($createdInvitation->code)->toBe('WAITER01')
        ->and($createdInvitation->inviteLink())->toBe(route('invitations.show', ['token' => str_repeat('T', 64)]))
        ->and($createdInvitation->invitation->invite_token)->toBeNull()
        ->and($createdInvitation->invitation->invite_code)->toBeNull()
        ->and($createdInvitation->invitation->invite_token_hash)->toBe(hash('sha256', str_repeat('T', 64)))
        ->and($createdInvitation->invitation->invite_code_hash)->toBe(hash('sha256', 'WAITER01'));

    $this->assertDatabaseMissing('invitations', ['invite_token' => str_repeat('T', 64)]);
    $this->assertDatabaseMissing('invitations', ['invite_code' => 'WAITER01']);
});

test('an authenticated matching recipient can accept a pending branch invitation once', function (): void {
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $invitedBy = User::factory()->create();
    $recipient = User::factory()->create(['email' => 'waiter@example.test']);
    $createdInvitation = app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
        'brand' => $brand,
        'branch' => $branch,
        'email' => ' Waiter@Example.Test ',
    ]);

    $this->actingAs($recipient)
        ->get(route('invitations.show', ['token' => $createdInvitation->token]))
        ->assertRedirect(route('invitations.pending'));

    $this->actingAs($recipient)
        ->get(route('invitations.pending'))
        ->assertOk()
        ->assertSee($organization->name)
        ->assertSee($branch->name)
        ->assertDontSee($createdInvitation->token);

    $this->actingAs($recipient)
        ->post(route('invitations.accept'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', __('invitations.messages.accepted'));

    $createdInvitation->invitation->refresh();

    expect($createdInvitation->invitation->status)->toBe(InvitationStatus::Accepted)
        ->and($createdInvitation->invitation->accepted_by_user_id)->toBe($recipient->id)
        ->and($createdInvitation->invitation->accepted_at)->not->toBeNull();

    $this->assertDatabaseHas('organization_users', [
        'organization_id' => $organization->id,
        'user_id' => $recipient->id,
        'role_id' => $role->id,
        'status' => OrganizationUserStatus::Active->value,
        'invited_by_user_id' => $invitedBy->id,
    ]);
    $this->assertDatabaseHas('branch_users', [
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'user_id' => $recipient->id,
        'role_id' => $role->id,
        'status' => OrganizationUserStatus::Active->value,
        'assigned_by_user_id' => $invitedBy->id,
    ]);

    $this->actingAs($recipient)
        ->post(route('invitations.accept'))
        ->assertGone();

    expect(OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $recipient->id)
        ->count())->toBe(1)
        ->and(BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $recipient->id)
            ->count())->toBe(1);
});

test('an unauthenticated recipient can review an invitation without exposing its bearer token', function (): void {
    $createdInvitation = createAcceptanceInvitation();

    $this->get(route('invitations.show', ['token' => $createdInvitation->token]))
        ->assertRedirect(route('invitations.pending'))
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get(route('invitations.pending'))
        ->assertOk()
        ->assertSee($createdInvitation->invitation->organization->name)
        ->assertSee('recipient@example.test')
        ->assertSee(__('invitations.actions.create_account_and_accept'))
        ->assertDontSee($createdInvitation->token)
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSessionHas('staff_invitation_id', $createdInvitation->invitation->id)
        ->assertSessionHas('url.intended', route('invitations.pending'));

    $this->assertGuest();
});

test('a new recipient can register and atomically accept a branch invitation', function (): void {
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $invitedBy = User::factory()->create();
    $createdInvitation = app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
        'brand' => $brand,
        'branch' => $branch,
        'email' => 'new.waiter@example.test',
    ]);

    $this->get(route('invitations.show', ['token' => $createdInvitation->token]))
        ->assertRedirect(route('invitations.pending'));
    $this->get(route('invitations.pending'))->assertOk();

    $this->post(route('invitations.register'), [
        'name' => 'New Waiter',
        'email' => ' NEW.WAITER@EXAMPLE.TEST ',
        'password' => 'StrongPassword2026!',
        'password_confirmation' => 'StrongPassword2026!',
    ])
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('staff_invitation_id')
        ->assertSessionMissing('url.intended')
        ->assertRedirect(route('dashboard'));

    $recipient = User::query()->where('email', 'new.waiter@example.test')->firstOrFail();

    $this->assertAuthenticatedAs($recipient);
    expect($createdInvitation->invitation->refresh()->status)->toBe(InvitationStatus::Accepted)
        ->and($createdInvitation->invitation->accepted_by_user_id)->toBe($recipient->id);

    $this->assertDatabaseHas('organization_users', [
        'organization_id' => $organization->id,
        'user_id' => $recipient->id,
        'role_id' => $role->id,
        'status' => OrganizationUserStatus::Active->value,
    ]);
    $this->assertDatabaseHas('branch_users', [
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'user_id' => $recipient->id,
        'role_id' => $role->id,
        'status' => OrganizationUserStatus::Active->value,
    ]);
});

test('invitation registration rejects a different email without creating partial records', function (): void {
    $createdInvitation = createAcceptanceInvitation('recipient@example.test');

    $this->get(route('invitations.show', ['token' => $createdInvitation->token]))
        ->assertRedirect(route('invitations.pending'));
    $this->get(route('invitations.pending'))->assertOk();

    $this->post(route('invitations.register'), [
        'name' => 'Wrong Recipient',
        'email' => 'other@example.test',
        'password' => 'StrongPassword2026!',
        'password_confirmation' => 'StrongPassword2026!',
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'other@example.test']);
    expect($createdInvitation->invitation->refresh()->status)->toBe(InvitationStatus::Pending)
        ->and(OrganizationUser::query()
            ->where('organization_id', $createdInvitation->invitation->organization_id)
            ->exists())->toBeFalse();
});

test('an existing recipient returns to a token free invitation page after login and accepts it', function (): void {
    $recipient = User::factory()->create(['email' => 'recipient@example.test']);
    $createdInvitation = createAcceptanceInvitation($recipient->email);

    $this->get(route('invitations.show', ['token' => $createdInvitation->token]))
        ->assertRedirect(route('invitations.pending'))
        ->assertSessionHas('url.intended', route('invitations.pending'));

    $this->post(route('login.store'), [
        'email' => $recipient->email,
        'password' => 'password',
    ])->assertRedirect(route('invitations.pending'));

    $this->get(route('invitations.pending'))
        ->assertOk()
        ->assertSee($createdInvitation->invitation->organization->name)
        ->assertSee(__('invitations.actions.accept'))
        ->assertDontSee($createdInvitation->token);

    $this->post(route('invitations.accept'))
        ->assertSessionMissing('staff_invitation_id')
        ->assertSessionMissing('url.intended')
        ->assertRedirect(route('dashboard'));

    expect($createdInvitation->invitation->refresh()->status)->toBe(InvitationStatus::Accepted)
        ->and($createdInvitation->invitation->accepted_by_user_id)->toBe($recipient->id);
});

test('invitation registration requires a valid invitation in the current session', function (): void {
    $this->post(route('invitations.register'), [
        'name' => 'Uninvited User',
        'email' => 'uninvited@example.test',
        'password' => 'StrongPassword2026!',
        'password_confirmation' => 'StrongPassword2026!',
    ])->assertGone();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'uninvited@example.test']);
});

test('a signed in user with a different email cannot inspect or accept an invitation', function (): void {
    $createdInvitation = createAcceptanceInvitation('recipient@example.test');
    $otherUser = User::factory()->create(['email' => 'other@example.test']);

    $this->actingAs($otherUser)
        ->get(route('invitations.show', ['token' => $createdInvitation->token]))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->post(route('invitations.accept'))
        ->assertGone();

    expect($createdInvitation->invitation->refresh()->status)->toBe(InvitationStatus::Pending);
});

test('expired revoked malformed and replayed invitation credentials are rejected without membership changes', function (): void {
    $recipient = User::factory()->create(['email' => 'recipient@example.test']);
    $expired = createAcceptanceInvitation('recipient@example.test', now()->subSecond());
    $revoked = createAcceptanceInvitation('recipient@example.test');
    $revoked->invitation->forceFill(['status' => InvitationStatus::Cancelled])->save();

    foreach ([$expired->token, $revoked->token, 'malformed'] as $token) {
        $this->actingAs($recipient)
            ->get(route('invitations.show', ['token' => $token]));

        $this->actingAs($recipient)
            ->post(route('invitations.accept'))
            ->assertGone();
    }

    expect(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse();
});

test('invitation endpoints are rate limited by credential and client address', function (): void {
    $createdInvitation = createAcceptanceInvitation();
    $recipient = User::factory()->create(['email' => 'recipient@example.test']);

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($recipient)
            ->get(route('invitations.show', ['token' => $createdInvitation->token]))
            ->assertRedirect(route('invitations.pending'));
    }

    $this->actingAs($recipient)
        ->get(route('invitations.show', ['token' => $createdInvitation->token]))
        ->assertTooManyRequests();
});

function createAcceptanceInvitation(
    string $email = 'recipient@example.test',
    ?DateTimeInterface $expiresAt = null,
): CreatedInvitation {
    $organization = Organization::factory()->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    return app(CreateInvitationAction::class)->handle($organization, $role, User::factory()->create(), [
        'email' => $email,
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);
}
