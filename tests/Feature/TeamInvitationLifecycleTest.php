<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\RegisterInvitationRecipientAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Invitation Test Group']);
    $this->owner = $this->owner->fresh();
    $this->role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
});

test('duplicate normalized invitations do not create another active credential or account', function (): void {
    $users = User::query()->count();
    $members = OrganizationUser::query()->count();
    app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => ' Team+Lunch@Example.Test ']);
    expect(fn () => app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'team+lunch@example.test']))->toThrow(ValidationException::class);
    expect(Invitation::query()->count())->toBe(1)->and(User::query()->count())->toBe($users)->and(OrganizationUser::query()->count())->toBe($members);
});

test('an already opened invitation cannot accept a rotated credential', function (): void {
    $recipient = User::factory()->create(['email' => 'stale@example.test']);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();
    app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation);
    $this->post(route('invitations.accept'), ['invitation_version' => $created->invitation->credentialVersion()])->assertGone();
    expect(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse();
    expect(fn () => app(AcceptInvitationAction::class)->handle($created->invitation, $recipient))->toThrow(DomainException::class);
});

test('registration rejects a rotated open form before creating a user', function (): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'new.stale@example.test']);
    $this->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();
    app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation);
    $this->post(route('invitations.register'), ['invitation_version' => $created->invitation->credentialVersion(), 'name' => 'Stale Recipient', 'email' => 'new.stale@example.test', 'password' => 'ValidPassword2026!', 'password_confirmation' => 'ValidPassword2026!'])->assertGone();
    expect(User::query()->where('email', 'new.stale@example.test')->exists())->toBeFalse();
});

test('acceptance preserves current active organization and branch roles', function (): void {
    $recipient = User::factory()->create();
    $branch = Branch::factory()->for($this->organization)->create();
    $differentRole = Role::query()->where('code', SystemRole::Cashier->value)->firstOrFail();
    OrganizationUser::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $recipient->id, 'role_id' => $differentRole->id, 'status' => OrganizationUserStatus::Active]);
    BranchUser::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $branch->id, 'user_id' => $recipient->id, 'role_id' => $differentRole->id, 'status' => OrganizationUserStatus::Active]);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email, 'branch' => $branch]);
    app(AcceptInvitationAction::class)->handle($created->invitation, $recipient);
    expect(OrganizationUser::query()->where('user_id', $recipient->id)->firstOrFail()->role_id)->toBe($differentRole->id)->and(BranchUser::query()->where('user_id', $recipient->id)->firstOrFail()->role_id)->toBe($differentRole->id);
});

test('a pending invitation never silently reactivates suspended participation', function (): void {
    $recipient = User::factory()->create();
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    OrganizationUser::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $recipient->id, 'role_id' => $this->role->id, 'status' => OrganizationUserStatus::Suspended]);
    expect(fn () => app(AcceptInvitationAction::class)->handle($created->invitation, $recipient))->toThrow(DomainException::class);
    expect($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('membership persistence veto rolls invitation and registration back together', function (): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'veto@example.test']);
    OrganizationUser::creating(fn (): bool => false);
    try {
        expect(fn () => app(RegisterInvitationRecipientAction::class)->handle($created->invitation, ['name' => 'Veto User', 'email' => 'veto@example.test', 'password' => 'ValidPassword2026!']))->toThrow(DomainException::class);
        expect(User::query()->where('email', 'veto@example.test')->exists())->toBeFalse()->and($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
    } finally {
        OrganizationUser::flushEventListeners();
    }
});

test('a reissue confirmation is single use even after the first response is lost', function (): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'reissue@example.test']);
    $version = $created->invitation->credentialVersion();
    $replacement = app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation, $version);
    expect(fn () => app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation->fresh(), $version))->toThrow(ValidationException::class);
    expect($created->invitation->fresh()->invite_token_hash)->toBe(hash('sha256', $replacement->token))
        ->and(AuditLog::query()->where('action', AuditLogAction::InvitationReissued->value)->count())->toBe(1);
});

test('an existing account switches safely from a mismatched email without losing consent context', function (): void {
    $recipient = User::factory()->create(['email' => 'invited@example.test']);
    $other = User::factory()->create(['email' => 'other@example.test']);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    $this->actingAs($other)->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();
    $this->get(route('invitations.pending'))->assertGone()->assertSee(__('invitations.actions.switch_account'))->assertDontSee($recipient->email);
    $this->post(route('invitations.switch-account'))->assertRedirect(route('login'))->assertSessionHas('staff_invitation_credential', hash('sha256', $created->token));
    $this->assertGuest();
    $this->get(route('invitations.pending'))->assertOk()->assertSee(__('invitations.account.existing_title'))->assertDontSee('register-invitation-button');
    $this->actingAs($recipient)->post(route('invitations.accept'), ['invitation_version' => $created->invitation->credentialVersion()])->assertRedirect(route('restaurant.dashboard'));
    expect($recipient->fresh()->email)->toBe('invited@example.test');
});

test('opening another invitation in the same browser cannot change the first forms consent', function (): void {
    $recipient = User::factory()->create();
    $first = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    $branch = Branch::factory()->for($this->organization)->create();
    $second = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email, 'branch' => $branch]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $first->token]))->assertRedirect();
    $this->get(route('invitations.show', ['token' => $second->token]))->assertRedirect();
    $this->post(route('invitations.accept'), ['invitation_version' => $first->invitation->credentialVersion()])->assertGone();
    expect(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse();
});

test('effective expiration is identical in rows and filters at the exact deadline', function (): void {
    $now = CarbonImmutable::parse('2026-09-15 12:00:00');
    $this->travelTo($now);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'expired@example.test', 'expires_at' => $now]);
    expect($created->invitation->effectiveStatus())->toBe(InvitationStatus::Expired)
        ->and(Invitation::query()->withEffectiveStatus(InvitationStatus::Expired)->count())->toBe(1)
        ->and(Invitation::query()->withEffectiveStatus(InvitationStatus::Pending)->count())->toBe(0);
});

test('invitation changes roll back when their audit record is rejected', function (): void {
    AuditLog::creating(fn (): bool => false);
    try {
        expect(fn () => app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'audit.veto@example.test']))->toThrow(RuntimeException::class);
        expect(Invitation::query()->where('email', 'audit.veto@example.test')->exists())->toBeFalse();
    } finally {
        AuditLog::flushEventListeners();
    }
});

test('reissuing an older expired invitation cannot duplicate a newer pending invitation', function (): void {
    $old = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'duplicate.reissue@example.test', 'expires_at' => now()->subMinute()]);
    $new = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'duplicate.reissue@example.test']);
    expect(fn () => app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $old->invitation))->toThrow(ValidationException::class);
    expect(Invitation::query()->acceptable()->count())->toBe(1)->and($new->invitation->fresh()->invite_token_hash)->toBe(hash('sha256', $new->token));
});

test('acceptance rechecks the current account email rather than a stale user model', function (): void {
    $recipient = User::factory()->create(['email' => 'before@example.test']);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    $recipient->fresh()->forceFill(['email' => 'after@example.test'])->save();
    expect(fn () => app(AcceptInvitationAction::class)->handle($created->invitation, $recipient))->toThrow(AuthorizationException::class);
    expect(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse()->and($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});
