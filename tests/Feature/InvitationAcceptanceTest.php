<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\CreatedInvitation;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('new invitation credentials are returned once and only digests are persisted', function (): void {
    [$invitedBy, $organization] = createInvitationOrganization('Credential Storage Group');
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    $createdInvitation = app(CreateInvitationAction::class)->handle(
        $organization,
        $role,
        $invitedBy,
        [
            'email' => 'recipient@example.test',
            'invite_token' => str_repeat('T', 64),
            'invite_code' => 'WAITER01',
        ],
    );

    expect($createdInvitation->token)->toBe(str_repeat('T', 64))
        ->and($createdInvitation->code)->toBe('WAITER01')
        ->and($createdInvitation->inviteLink())->toBe(route('invitations.show', ['token' => str_repeat('T', 64)]))
        ->and($createdInvitation->invitation->invite_token_hash)->toBe(hash('sha256', str_repeat('T', 64)))
        ->and($createdInvitation->invitation->invite_code_hash)->toBe(hash('sha256', 'WAITER01'));
});

test('an authenticated matching recipient can accept a pending branch invitation once', function (): void {
    [$invitedBy, $organization] = createInvitationOrganization('Atomic Acceptance Group');
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
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
            ->count())->toBe(1)
        ->and(AuditLog::query()
            ->where('action', AuditLogAction::InvitationAccepted->value)
            ->where('entity_type', 'invitation')
            ->where('entity_id', $createdInvitation->invitation->id)
            ->where('user_id', $recipient->id)
            ->count())->toBe(1);

    $auditLog = AuditLog::query()
        ->where('action', AuditLogAction::InvitationAccepted->value)
        ->where('entity_id', $createdInvitation->invitation->id)
        ->firstOrFail();
    $auditPayload = json_encode([$auditLog->old_values, $auditLog->new_values], JSON_THROW_ON_ERROR);

    expect($auditPayload)
        ->not->toContain($createdInvitation->token)
        ->not->toContain('invite_token')
        ->not->toContain('hash');
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
    [$invitedBy, $organization] = createInvitationOrganization('Registration Acceptance Group');
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
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
            ->count())->toBe(1);
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
        ->assertRedirect(route('invitations.pending'));

    $this->actingAs($otherUser)
        ->get(route('invitations.pending'))
        ->assertGone()
        ->assertSee(__('invitations.states.unavailable_title'))
        ->assertDontSee('recipient@example.test')
        ->assertDontSee($createdInvitation->invitation->organization->name)
        ->assertDontSee($createdInvitation->token);

    $this->actingAs($otherUser)
        ->post(route('invitations.accept'))
        ->assertGone();

    expect($createdInvitation->invitation->refresh()->status)->toBe(InvitationStatus::Pending);
});

test('expired revoked malformed and replayed links render localized token-free states', function (): void {
    $recipient = User::factory()->create(['email' => 'recipient@example.test']);
    $expired = createAcceptanceInvitation($recipient->email, now()->subSecond());
    $revoked = createAcceptanceInvitation($recipient->email);
    $revoked->invitation->forceFill(['status' => InvitationStatus::Cancelled])->saveOrFail();
    $accepted = createAcceptanceInvitation($recipient->email);

    app(AcceptInvitationAction::class)->handle($accepted->invitation, $recipient);

    $this->actingAs($recipient)
        ->get(route('invitations.show', ['token' => $expired->token]))
        ->assertRedirect(route('invitations.pending'));
    $this->actingAs($recipient)
        ->get(route('invitations.pending'))
        ->assertGone()
        ->assertSee(__('invitations.states.expired_title'))
        ->assertSee(__('invitations.states.expired_message'))
        ->assertDontSee($expired->token)
        ->assertDontSee($expired->invitation->organization->name);

    foreach ([$revoked->token, 'malformed'] as $unavailableToken) {
        $this->actingAs($recipient)
            ->get(route('invitations.show', ['token' => $unavailableToken]))
            ->assertRedirect(route('invitations.pending'));
        $this->actingAs($recipient)
            ->get(route('invitations.pending'))
            ->assertGone()
            ->assertSee(__('invitations.states.unavailable_title'))
            ->assertSee(__('invitations.states.unavailable_message'))
            ->assertDontSee($unavailableToken);
    }

    $this->actingAs($recipient)
        ->get(route('invitations.show', ['token' => $accepted->token]))
        ->assertRedirect(route('invitations.pending'));
    $this->actingAs($recipient)
        ->get(route('invitations.pending'))
        ->assertOk()
        ->assertSee(__('invitations.states.accepted_title'))
        ->assertSee(__('invitations.states.accepted_message'))
        ->assertDontSee($accepted->token)
        ->assertDontSee($accepted->invitation->organization->name);
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

test('invitation endpoints enforce an ip budget across changing credentials', function (): void {
    foreach (range(1, 30) as $attempt) {
        $token = str_pad((string) $attempt, 64, 'A');

        $this->get(route('invitations.show', ['token' => $token]))
            ->assertRedirect(route('invitations.pending'));
    }

    $this->get(route('invitations.show', ['token' => str_repeat('Z', 64)]))
        ->assertTooManyRequests();
});

test('acceptance fails closed for a legacy invitation granting superadmin', function (): void {
    $recipient = User::factory()->create(['email' => 'recipient@example.test']);
    $organization = Organization::factory()->create();
    $superadmin = Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail();
    $invitation = Invitation::factory()
        ->forOrganization($organization)
        ->forRole($superadmin)
        ->pending()
        ->create(['email' => $recipient->email]);

    expect(fn () => app(AcceptInvitationAction::class)->handle($invitation, $recipient))
        ->toThrow(DomainException::class);

    expect($recipient->fresh()->isSuperadmin())->toBeFalse()
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('acceptance fails closed when a legacy branch invitation crosses tenant scope', function (): void {
    $recipient = User::factory()->create(['email' => 'recipient@example.test']);
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherBrand = Brand::factory()->for($otherOrganization)->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->for($otherBrand)->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $invitation = Invitation::factory()
        ->forOrganization($organization)
        ->forRole($role)
        ->pending()
        ->create([
            'brand_id' => $otherBrand->id,
            'branch_id' => $otherBranch->id,
            'email' => $recipient->email,
        ]);

    expect(fn () => app(AcceptInvitationAction::class)->handle($invitation, $recipient))
        ->toThrow(DomainException::class);

    expect(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse()
        ->and(BranchUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse()
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

function createAcceptanceInvitation(
    string $email = 'recipient@example.test',
    ?DateTimeInterface $expiresAt = null,
): CreatedInvitation {
    [$invitedBy, $organization] = createInvitationOrganization('Acceptance Helper Group');
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    return app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
        'email' => $email,
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);
}

/** @return array{User, Organization} */
function createInvitationOrganization(string $name): array
{
    $invitedBy = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($invitedBy, ['name' => $name]);

    return [$invitedBy->fresh(), $organization];
}
