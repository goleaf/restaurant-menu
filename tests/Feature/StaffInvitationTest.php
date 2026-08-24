<?php

use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('invitation statuses are fixed', function () {
    expect(InvitationStatus::values())->toBe([
        'pending',
        'accepted',
        'expired',
        'cancelled',
        'rejected',
    ]);
});

test('invitations table stores staff invitation scope and credentials', function () {
    expect(Schema::hasTable('invitations'))->toBeTrue();
    expect(Schema::hasColumns('invitations', [
        'organization_id',
        'brand_id',
        'branch_id',
        'role_id',
        'email',
        'phone',
        'invite_token_hash',
        'invite_code_hash',
        'expires_at',
        'status',
        'invited_by_user_id',
    ]))->toBeTrue();
});

test('staff invitation can target organization brand and branch', function () {
    Mail::fake();
    $invitedBy = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($invitedBy, [
        'name' => 'Branch Invitation Group',
    ]);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create();
    $role = Role::query()->where('code', 'waiter')->firstOrFail();
    $inviteToken = str_repeat('T', 64);

    $createdInvitation = app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
        'brand' => $brand,
        'branch' => $branch,
        'email' => 'waiter@example.test',
        'phone' => '+37060000000',
        'invite_token' => $inviteToken,
        'invite_code' => 'WAITER01',
        'expires_at' => now()->addDays(3),
    ]);

    $invitation = $createdInvitation->invitation;

    expect($invitation)->toBeInstanceOf(Invitation::class);
    expect($invitation->organization_id)->toBe($organization->id);
    expect($invitation->brand_id)->toBe($brand->id);
    expect($invitation->branch_id)->toBe($branch->id);
    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->email)->toBe('waiter@example.test');
    expect($invitation->phone)->toBe('+37060000000');
    expect($createdInvitation->token)->toBe($inviteToken);
    expect($createdInvitation->code)->toBe('WAITER01');
    expect($invitation->status)->toBe(InvitationStatus::Pending);
    expect($invitation->invited_by_user_id)->toBe($invitedBy->id);
    expect($invitation->canBeAccepted())->toBeTrue();

    $auditLog = AuditLog::query()
        ->where('action', AuditLogAction::InvitationCreated->value)
        ->where('entity_id', $invitation->id)
        ->firstOrFail();
    $auditPayload = json_encode([$auditLog->old_values, $auditLog->new_values], JSON_THROW_ON_ERROR);

    expect($auditPayload)
        ->not->toContain($inviteToken)
        ->not->toContain('invite_token')
        ->not->toContain('hash');

    Mail::assertNothingOutgoing();
});

test('staff invitation can target only organization with generated token and code', function () {
    $invitedBy = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($invitedBy, [
        'name' => 'Organization Invitation Group',
    ]);
    $role = Role::query()->where('code', 'director')->firstOrFail();

    $createdInvitation = app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
        'email' => 'director@example.test',
    ]);
    $invitation = $createdInvitation->invitation;

    expect($invitation->organization_id)->toBe($organization->id);
    expect($invitation->brand_id)->toBeNull();
    expect($invitation->branch_id)->toBeNull();
    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->email)->toBe('director@example.test');
    expect($invitation->phone)->toBeNull();
    expect($createdInvitation->token)->toHaveLength(64);
    expect($createdInvitation->code)->toHaveLength(8);
    expect($invitation->expires_at)->not->toBeNull();
    expect($invitation->status)->toBe(InvitationStatus::Pending);
});

test('staff invitations require a recipient email at the action boundary', function () {
    $invitedBy = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($invitedBy, [
        'name' => 'Bound Recipient Group',
    ]);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    expect(fn () => app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
        'phone' => '+37060000000',
    ]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
            'email' => 'not-an-email',
        ]))->toThrow(InvalidArgumentException::class);

    expect(Invitation::query()->count())->toBe(0);
});

test('staff invitation creation authorizes the actor and target role at the action boundary', function () {
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, [
        'name' => 'Authorization Boundary Group',
    ]);
    $outsider = User::factory()->create();
    $waiter = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $ownerRole = Role::query()->where('code', SystemRole::Owner->value)->firstOrFail();

    expect(fn () => app(CreateInvitationAction::class)->handle($organization, $waiter, $outsider, [
        'email' => 'outsider-attempt@example.test',
    ]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CreateInvitationAction::class)->handle($organization, $ownerRole, $owner, [
            'email' => 'owner-escalation@example.test',
        ]))->toThrow(AuthorizationException::class);

    expect(Invitation::query()->count())->toBe(0);
});

test('staff invitation rejects brand from another organization', function () {
    $invitedBy = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($invitedBy, [
        'name' => 'Scoped Brand Group',
    ]);
    $otherOrganization = Organization::factory()->create();
    $brand = Brand::factory()->for($otherOrganization)->create();
    $role = Role::query()->where('code', 'waiter')->firstOrFail();
    expect(fn () => app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
        'brand' => $brand,
        'email' => 'waiter@example.test',
    ]))->toThrow(InvalidArgumentException::class);
});

test('staff invitation rejects branch outside the selected organization or brand', function () {
    $invitedBy = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($invitedBy, [
        'name' => 'Scoped Branch Group',
    ]);
    $brand = Brand::factory()->for($organization)->create();
    $otherOrganization = Organization::factory()->create();
    $otherBrand = Brand::factory()->for($otherOrganization)->create();
    $otherBranch = Branch::factory()
        ->for($otherOrganization)
        ->for($otherBrand)
        ->create();
    $role = Role::query()->where('code', 'waiter')->firstOrFail();
    expect(fn () => app(CreateInvitationAction::class)->handle($organization, $role, $invitedBy, [
        'brand' => $brand,
        'branch' => $otherBranch,
        'email' => 'waiter@example.test',
    ]))->toThrow(InvalidArgumentException::class);
});
