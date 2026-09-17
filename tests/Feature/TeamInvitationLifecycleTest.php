<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\CancelInvitationAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\RegisterInvitationRecipientAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\InvitationPage;

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
    $snapshot = InvitationPage::open($this);
    app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation);
    InvitationPage::call($this, 'accept', snapshot: $snapshot)->assertGone();
    expect(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse();
    expect(fn () => app(AcceptInvitationAction::class)->handle($created->invitation, $recipient))->toThrow(DomainException::class);
});

test('registration rejects a rotated open form before creating a user', function (): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'new.stale@example.test']);
    $this->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();
    $snapshot = InvitationPage::open($this);
    app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation);
    InvitationPage::call($this, 'register', ['name' => 'Stale Recipient', 'email' => 'new.stale@example.test', 'password' => 'ValidPassword2026!', 'password_confirmation' => 'ValidPassword2026!'], $snapshot)->assertGone();
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
    InvitationPage::call($this, 'switchAccount')->assertOk()->assertJsonPath('components.0.effects.redirect', route('login'))->assertSessionHas('staff_invitation_credential', hash('sha256', $created->token));
    $this->assertGuest();
    $this->get(route('invitations.pending'))->assertOk()->assertSee(__('invitations.account.existing_title'))->assertDontSee('register-invitation-button');
    InvitationPage::call($this->actingAs($recipient), 'accept', [])->assertOk()->assertJsonPath('components.0.effects.redirect', route('restaurant.dashboard'));
    expect($recipient->fresh()->email)->toBe('invited@example.test');
});

test('opening another invitation in the same browser cannot change the first forms consent', function (): void {
    $recipient = User::factory()->create();
    $first = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    $branch = Branch::factory()->for($this->organization)->create();
    $second = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email, 'branch' => $branch]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $first->token]))->assertRedirect();
    $snapshot = InvitationPage::open($this);
    $this->get(route('invitations.show', ['token' => $second->token]))->assertRedirect();
    InvitationPage::call($this, 'accept', snapshot: $snapshot)->assertStatus(409);
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

test('acceptance binds the reviewed role and scope inside the transaction', function (): void {
    $recipient = User::factory()->create();
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    $replacementRole = Role::query()->where('code', SystemRole::RestaurantAdmin->value)->firstOrFail();
    $created->invitation->fresh()->forceFill(['role_id' => $replacementRole->id])->save();

    expect(fn () => app(AcceptInvitationAction::class)->handle($created->invitation, $recipient))->toThrow(DomainException::class);
    expect(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse()
        ->and($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('registration binds the reviewed role and scope inside the transaction', function (): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'changed.consent@example.test']);
    $replacementRole = Role::query()->where('code', SystemRole::RestaurantAdmin->value)->firstOrFail();
    $created->invitation->fresh()->forceFill(['role_id' => $replacementRole->id])->save();

    expect(fn () => app(RegisterInvitationRecipientAction::class)->handle($created->invitation, [
        'name' => 'Changed Consent', 'email' => 'changed.consent@example.test', 'password' => 'ValidPassword2026!',
    ]))->toThrow(DomainException::class);
    expect(User::query()->where('email', 'changed.consent@example.test')->exists())->toBeFalse()
        ->and($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('invitation creation resolves the current assignable role instead of a stale role model', function (): void {
    $this->role->fresh()->forceFill(['sort_order' => 0])->save();

    expect(fn () => app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'stale.role@example.test']))->toThrow(AuthorizationException::class);
    expect(Invitation::query()->where('email', 'stale.role@example.test')->exists())->toBeFalse();
});

test('one account consents separately to each organization without changing its existing role', function (): void {
    $recipient = User::factory()->create(['email' => 'two.groups@example.test']);
    $otherOwner = User::factory()->create();
    $otherOrganization = app(CreateOrganizationAction::class)->handle($otherOwner, ['name' => 'Separate Consent Group']);
    $cashier = Role::query()->where('code', SystemRole::Cashier->value)->firstOrFail();
    OrganizationUser::factory()->create([
        'organization_id' => $this->organization->id, 'user_id' => $recipient->id,
        'role_id' => $cashier->id, 'status' => OrganizationUserStatus::Active,
    ]);

    $created = app(CreateInvitationAction::class)->handle($otherOrganization, $this->role, $otherOwner, ['email' => $recipient->email]);
    expect($recipient->organizationMemberships()->count())->toBe(1);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();
    $this->get(route('invitations.pending'))->assertOk()->assertSee($otherOrganization->name);
    expect($recipient->organizationMemberships()->count())->toBe(1);
    InvitationPage::call($this, 'accept', [])->assertOk()->assertJsonStructure(['components' => [['effects' => ['redirect']]]]);

    expect($recipient->organizationMemberships()->count())->toBe(2)
        ->and($recipient->organizationMemberships()->where('organization_id', $this->organization->id)->sole()->role_id)->toBe($cashier->id)
        ->and($recipient->organizationMemberships()->where('organization_id', $otherOrganization->id)->sole()->role_id)->toBe($this->role->id)
        ->and(User::query()->where('email', $recipient->email)->count())->toBe(1);
});

test('a branch invitation cannot restore a suspended branch while organization membership stays active', function (): void {
    $recipient = User::factory()->create();
    $branch = Branch::factory()->for($this->organization)->create();
    OrganizationUser::factory()->create([
        'organization_id' => $this->organization->id, 'user_id' => $recipient->id,
        'role_id' => $this->role->id, 'status' => OrganizationUserStatus::Active,
    ]);
    $assignment = BranchUser::factory()->create([
        'organization_id' => $this->organization->id, 'branch_id' => $branch->id,
        'user_id' => $recipient->id, 'role_id' => $this->role->id, 'status' => OrganizationUserStatus::Suspended,
    ]);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email, 'branch' => $branch]);

    expect(fn () => app(AcceptInvitationAction::class)->handle($created->invitation, $recipient))->toThrow(DomainException::class);
    expect($assignment->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
        ->and($recipient->organizationMemberships()->sole()->status)->toBe(OrganizationUserStatus::Active)
        ->and($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('acceptance opens the workspace of the preserved scoped role', function (SystemRole $roleCode, KitchenDepartmentType $departmentType, string $routeName): void {
    $recipient = User::factory()->create();
    $branch = Branch::factory()->for($this->organization)->create();
    $role = Role::query()->where('code', $roleCode->value)->firstOrFail();
    OrganizationUser::factory()->create([
        'organization_id' => $this->organization->id, 'user_id' => $recipient->id,
        'role_id' => $role->id, 'status' => OrganizationUserStatus::Active,
    ]);
    BranchUser::factory()->create([
        'organization_id' => $this->organization->id, 'branch_id' => $branch->id,
        'user_id' => $recipient->id, 'role_id' => $role->id, 'status' => OrganizationUserStatus::Active,
    ]);
    $department = KitchenDepartment::factory()->for($branch)->forType($departmentType)->create();
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email, 'branch' => $branch]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();
    $this->get(route('invitations.pending'))->assertOk()
        ->assertSee($roleCode->localizedLabel())
        ->assertSee(__('invitations.access.existing_roles'));

    InvitationPage::call($this, 'accept', [])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', route($routeName, ['department' => $department->id]));
    expect($recipient->organizationMemberships()->sole()->role_id)->toBe($role->id)
        ->and($recipient->branchAssignments()->sole()->role_id)->toBe($role->id);
})->with([
    [SystemRole::Cook, KitchenDepartmentType::Kitchen, 'restaurant.kitchen.dashboard'],
    [SystemRole::Bartender, KitchenDepartmentType::Bar, 'restaurant.bar.dashboard'],
]);

test('an organization invitation does not redirect into another organizations waiter workspace', function (): void {
    $recipient = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherOrganization = app(CreateOrganizationAction::class)->handle($otherOwner, ['name' => 'Existing Waiter Workplace']);
    Branch::factory()->for($otherOrganization)->create();
    OrganizationUser::factory()->create([
        'organization_id' => $otherOrganization->id, 'user_id' => $recipient->id,
        'role_id' => $this->role->id, 'status' => OrganizationUserStatus::Active,
    ]);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();

    InvitationPage::call($this, 'accept', [])->assertOk()->assertJsonPath('components.0.effects.redirect', route('restaurant.dashboard'));
});

test('an organization invitation selects a waiter branch from its own organization', function (): void {
    $recipient = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherOrganization = app(CreateOrganizationAction::class)->handle($otherOwner, ['name' => 'Earlier Waiter Workplace']);
    $otherBranch = Branch::factory()->for($otherOrganization)->create(['name' => 'Aardvark Branch']);
    $invitedBranch = Branch::factory()->for($this->organization)->create(['name' => 'Zebra Branch']);
    OrganizationUser::factory()->create([
        'organization_id' => $otherOrganization->id, 'user_id' => $recipient->id,
        'role_id' => $this->role->id, 'status' => OrganizationUserStatus::Active,
    ]);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email]);
    $this->actingAs($recipient)->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();

    InvitationPage::call($this, 'accept', [])
        ->assertOk()->assertJsonPath('components.0.effects.redirect', route('restaurant.waiter.dashboard', ['branch' => $invitedBranch->id]));
    expect($recipient->fresh()->canAccessBranch($otherBranch))->toBeTrue();
});

test('a stale superadmin form cannot create or reissue invitations in an archived organization', function (string $operation): void {
    $superadmin = User::factory()->create();
    $superadmin->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $superadmin, ['email' => 'archived.scope@example.test']);
    $digest = $created->invitation->invite_token_hash;
    $this->organization->fresh()->delete();
    $auditCount = AuditLog::query()->count();

    expect(fn () => $operation === 'create'
        ? app(CreateInvitationAction::class)->handle($this->organization, $this->role, $superadmin, ['email' => 'new.archived.scope@example.test'])
        : app(ReissueInvitationAction::class)->handle($superadmin, $this->organization, $created->invitation))
        ->toThrow(DomainException::class);
    expect(Invitation::query()->count())->toBe(1)
        ->and($created->invitation->fresh()->invite_token_hash)->toBe($digest)
        ->and(AuditLog::query()->count())->toBe($auditCount);
})->with(['create', 'reissue']);

test('each invitation mutation rolls back if its audit write is vetoed', function (string $operation): void {
    $recipient = User::factory()->create();
    $branch = Branch::factory()->for($this->organization)->create();
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => $recipient->email, 'branch' => $branch]);
    $digest = $created->invitation->invite_token_hash;
    $auditCount = AuditLog::query()->count();
    AuditLog::creating(fn (): bool => false);

    try {
        expect(fn () => match ($operation) {
            'accept' => app(AcceptInvitationAction::class)->handle($created->invitation, $recipient),
            'cancel' => app(CancelInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation),
            'reissue' => app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation),
        })->toThrow(RuntimeException::class);

        expect($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending)
            ->and($created->invitation->fresh()->invite_token_hash)->toBe($digest)
            ->and(OrganizationUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse()
            ->and(BranchUser::query()->where('user_id', $recipient->id)->exists())->toBeFalse()
            ->and(AuditLog::query()->count())->toBe($auditCount);
    } finally {
        AuditLog::flushEventListeners();
    }
})->with(['accept', 'cancel', 'reissue']);

test('failed invitation responses retain private security headers', function (string $failure): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'private.response@example.test']);
    $this->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();

    if ($failure === 'gone') {
        app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation);
    }
    if ($failure === 'throttled') {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->get(route('invitations.pending'));
        }
        $response = $this->get(route('invitations.pending'))->assertTooManyRequests();
    } else {
        $response = InvitationPage::call($this->from(route('invitations.pending')), 'register', [
            'name' => '',
            'email' => 'private.response@example.test', 'password' => 'short', 'password_confirmation' => 'different',
        ]);
        if ($failure === 'gone') {
            $response->assertGone();
        } else {
            $response->assertOk();
            expect(InvitationPage::errors($response))->toHaveKeys(['form.name', 'form.password']);
        }
    }

    $response->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with(['gone', 'validation', 'throttled']);

test('new invitation accounts retain their selected language in the workplace', function (string $locale): void {
    $branch = Branch::factory()->for($this->organization)->withDefaultSettings()->create();
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, [
        'email' => 'locale.recipient@example.test', 'branch' => $branch,
    ]);
    $this->get(route('invitations.show', ['token' => $created->token, 'lang' => $locale]))->assertRedirect();
    $this->get(route('invitations.pending'))->assertOk()->assertSee('lang="'.$locale.'"', false);
    InvitationPage::call($this, 'register', [
        'name' => 'Locale Recipient', 'email' => 'locale.recipient@example.test',
        'password' => 'ValidPassword2026!', 'password_confirmation' => 'ValidPassword2026!',
    ])->assertOk()->assertJsonPath('components.0.effects.redirect', route('restaurant.waiter.dashboard', ['branch' => $branch->id]));

    expect(User::query()->where('email', 'locale.recipient@example.test')->sole()->locale)->toBe($locale);
    $this->get(route('restaurant.waiter.dashboard', ['branch' => $branch->id]))
        ->assertOk()->assertSee('lang="'.$locale.'"', false);
})->with(['en', 'lt', 'ru']);

test('invitation account creation normalizes the current application language', function (string $applicationLocale, string $expectedLocale): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'normalized.locale@example.test']);
    app()->setLocale($applicationLocale);

    $recipient = app(RegisterInvitationRecipientAction::class)->handle($created->invitation, [
        'name' => 'Normalized Locale', 'email' => 'normalized.locale@example.test', 'password' => 'ValidPassword2026!',
    ]);

    expect($recipient->fresh()->locale)->toBe($expectedLocale);
})->with([['lt-LT', 'lt'], ['unsupported', 'en']]);

test('invitation registration retry preserves a newer account language preference', function (): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'retry.locale@example.test']);
    $data = ['name' => 'Retry Locale', 'email' => 'retry.locale@example.test', 'password' => 'ValidPassword2026!'];
    $register = app(RegisterInvitationRecipientAction::class);
    $recipient = $register->handle($created->invitation, $data);
    $recipient->forceFill(['locale' => 'ru'])->save();
    app()->setLocale('lt');

    $retry = $register->handle($created->invitation, $data);

    expect($retry->id)->toBe($recipient->id)->and($retry->fresh()->locale)->toBe('ru')
        ->and(User::query()->where('email', $recipient->email)->count())->toBe(1);
});

test('invitation registration validation uses the selected language and preserves safe input', function (string $locale, string $field, string $value, string $messageKey): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'validation.locale@example.test']);
    $this->get(route('invitations.show', ['token' => $created->token, 'lang' => $locale]))->assertRedirect();
    $input = [
        'name' => 'Validation Recipient', 'email' => 'validation.locale@example.test',
        'password' => 'ValidPassword2026!', 'password_confirmation' => 'ValidPassword2026!',
    ];
    $input[$field] = $value;
    if ($messageKey === 'password_min') {
        $input['password_confirmation'] = $value;
    }
    $attributeKeys = [
        'name' => 'ui.auth.register.full_name',
        'email' => 'ui.auth.forgot_password.email_address',
        'password' => 'ui.auth.confirm_password.password',
    ];
    $expected = __('invitations.validation.'.$messageKey, ['attribute' => __($attributeKeys[$field]), 'min' => 8]);
    expect($expected)->not->toBe('invitations.validation.'.$messageKey);

    $response = InvitationPage::call($this->from(route('invitations.pending')), 'register', $input);

    $response->assertOk()
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');
    expect(InvitationPage::errors($response)['form.'.$field])->toContain($expected)
        ->and(InvitationPage::form($response)['name'])->toBe($input['name'])
        ->and(InvitationPage::form($response)['email'])->toBe($input['email'])
        ->and(InvitationPage::form($response)['password'])->toBe('')
        ->and(InvitationPage::form($response)['password_confirmation'])->toBe('');
    expect(User::query()->where('email', 'validation.locale@example.test')->exists())->toBeFalse()
        ->and($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
})->with(['en', 'lt', 'ru'])->with([
    'minimum password' => ['password', 'short', 'password_min'],
    'password confirmation' => ['password', 'DifferentPassword2026!', 'password_confirmed'],
    'required name' => ['name', '', 'required'],
    'required email' => ['email', '', 'required'],
    'required password' => ['password', '', 'required'],
]);

test('an invitation cannot enumerate accounts through mismatched registration emails', function (): void {
    $foreign = User::factory()->create(['email' => 'foreign.account@example.test']);
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->role, $this->owner, ['email' => 'intended.recipient@example.test']);
    $this->get(route('invitations.show', ['token' => $created->token]))->assertRedirect();
    $userCount = User::query()->count();
    $memberCount = OrganizationUser::query()->count();
    $input = [
        'name' => 'Unaccepted Recipient',
        'password' => 'ValidPassword2026!', 'password_confirmation' => 'ValidPassword2026!',
    ];

    $existing = InvitationPage::call($this->from(route('invitations.pending')), 'register', [...$input, 'email' => $foreign->email]);
    $existing->assertOk();
    expect(InvitationPage::errors($existing)['form.email'])->toBe([__('invitations.validation.email_mismatch')]);
    $unknown = InvitationPage::call($this->from(route('invitations.pending')), 'register', [...$input, 'email' => 'unknown.account@example.test']);
    $unknown->assertOk();
    expect(InvitationPage::errors($unknown)['form.email'])->toBe([__('invitations.validation.email_mismatch')]);

    expect(User::query()->count())->toBe($userCount)
        ->and(OrganizationUser::query()->count())->toBe($memberCount)
        ->and($created->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});
