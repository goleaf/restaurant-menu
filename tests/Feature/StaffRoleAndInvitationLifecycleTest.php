<?php

use App\Actions\Invitations\CancelInvitationAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as BranchStaffIndex;
use App\Livewire\Organizations\Staff\Index as OrganizationStaffIndex;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('organization owner changes a member to an assignable role with an audit reason', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    $waiter = roleForLifecycle(SystemRole::Waiter);
    $director = roleForLifecycle(SystemRole::Director);
    $membership = OrganizationUser::factory()->forOrganization($organization)->forRole($waiter)->active()->create();

    $updated = app(UpdateOrganizationStaffRoleAction::class)->handle(
        $owner,
        $organization,
        $membership,
        $director,
        'Promoted to lead the restaurant team.',
    );

    expect($updated->role_id)->toBe($director->id)
        ->and($membership->fresh()->role_id)->toBe($director->id);

    $auditLog = AuditLog::query()->latest('id')->firstOrFail();

    expect($auditLog->action)->toBe(AuditLogAction::StaffRoleChanged)
        ->and($auditLog->organization_id)->toBe($organization->id)
        ->and($auditLog->old_values['role_id'])->toBe($waiter->id)
        ->and($auditLog->new_values['role_id'])->toBe($director->id)
        ->and($auditLog->new_values['reason'])->toBe('Promoted to lead the restaurant team.');
});

test('branch manager changes a branch role without changing the organization role', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    [$brand, $branch] = createBranchRoleLifecycleContext($organization);
    $waiter = roleForLifecycle(SystemRole::Waiter);
    $bartender = roleForLifecycle(SystemRole::Bartender);
    $staffUser = User::factory()->create();
    $organizationMembership = OrganizationUser::factory()->forOrganization($organization)->forUser($staffUser)->forRole($waiter)->active()->create();
    $branchMembership = BranchUser::factory()->forBranch($branch)->forUser($staffUser)->forRole($waiter)->active()->create();

    $updated = app(UpdateBranchStaffRoleAction::class)->handle(
        $owner,
        $branch,
        $branchMembership,
        $bartender,
        'Assigned to the branch bar team.',
    );

    expect($updated->role_id)->toBe($bartender->id)
        ->and($organizationMembership->fresh()->role_id)->toBe($waiter->id);

    $this->actingAs($owner)
        ->get(route('organizations.brands.branches.staff.index', [$organization, $brand, $branch]))
        ->assertOk();
});

test('restaurant administrator cannot promote staff above their own role', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    $restaurantAdminRole = roleForLifecycle(SystemRole::RestaurantAdmin);
    $directorRole = roleForLifecycle(SystemRole::Director);
    $waiterRole = roleForLifecycle(SystemRole::Waiter);
    $restaurantAdmin = User::factory()->create();
    OrganizationUser::factory()
        ->forOrganization($organization)
        ->forUser($restaurantAdmin)
        ->forRole($restaurantAdminRole)
        ->active()
        ->create();
    $membership = OrganizationUser::factory()
        ->forOrganization($organization)
        ->forRole($waiterRole)
        ->active()
        ->create();

    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle(
        $restaurantAdmin,
        $organization,
        $membership,
        $directorRole,
        'Attempted privilege escalation.',
    ))->toThrow(AuthorizationException::class);

    expect($membership->fresh()->role_id)->toBe($waiterRole->id)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('superadmin role cannot be assigned through organization or branch staff actions', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    [, $branch] = createBranchRoleLifecycleContext($organization);
    $superadmin = roleForLifecycle(SystemRole::Superadmin);
    $waiter = roleForLifecycle(SystemRole::Waiter);
    $organizationMembership = OrganizationUser::factory()->forOrganization($organization)->forRole($waiter)->create();
    $branchMembership = BranchUser::factory()->forBranch($branch)->forUser($organizationMembership->user)->forRole($waiter)->create();

    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle(
        $owner,
        $organization,
        $organizationMembership,
        $superadmin,
        'Attempted privileged assignment.',
    ))->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateBranchStaffRoleAction::class)->handle(
            $owner,
            $branch,
            $branchMembership,
            $superadmin,
            'Attempted privileged assignment.',
        ))->toThrow(ValidationException::class);

    expect($organizationMembership->fresh()->role_id)->toBe($waiter->id)
        ->and($branchMembership->fresh()->role_id)->toBe($waiter->id);
});

test('actor cannot change their own role', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    $director = roleForLifecycle(SystemRole::Director);
    $ownerRole = roleForLifecycle(SystemRole::Owner);
    $ownerMembership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $owner->id)
        ->firstOrFail();

    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle(
        $owner,
        $organization,
        $ownerMembership,
        $director,
        'Trying to change my own organization access.',
    ))->toThrow(ValidationException::class);

    expect($ownerMembership->fresh()->role_id)->toBe($ownerRole->id);
});

test('the last active organization owner cannot lose the owner role', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    $superadmin = User::factory()->create();
    $superadmin->roles()->sync([roleForLifecycle(SystemRole::Superadmin)->id]);
    $director = roleForLifecycle(SystemRole::Director);
    $ownerMembership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $owner->id)
        ->firstOrFail();

    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle(
        $superadmin,
        $organization,
        $ownerMembership,
        $director,
        'Administrative owner role correction.',
    ))->toThrow(ValidationException::class);

    expect($ownerMembership->fresh()->role_id)->toBe(roleForLifecycle(SystemRole::Owner)->id);
});

test('cross organization and cross branch identifiers are rejected without mutation', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    [, $branch] = createBranchRoleLifecycleContext($organization);
    [, $otherOrganization] = createOrganizationRoleLifecycleContext();
    [, $otherBranch] = createBranchRoleLifecycleContext($otherOrganization);
    $waiter = roleForLifecycle(SystemRole::Waiter);
    $director = roleForLifecycle(SystemRole::Director);
    $foreignOrganizationMembership = OrganizationUser::factory()->forOrganization($otherOrganization)->forRole($waiter)->create();
    $foreignBranchMembership = BranchUser::factory()->forBranch($otherBranch)->forRole($waiter)->create();

    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle(
        $owner,
        $organization,
        $foreignOrganizationMembership,
        $director,
        'Cross tenant role update attempt.',
    ))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(UpdateBranchStaffRoleAction::class)->handle(
            $owner,
            $branch,
            $foreignBranchMembership,
            $director,
            'Cross branch role update attempt.',
        ))->toThrow(ModelNotFoundException::class);

    expect($foreignOrganizationMembership->fresh()->role_id)->toBe($waiter->id)
        ->and($foreignBranchMembership->fresh()->role_id)->toBe($waiter->id);
});

test('only pending invitations can be cancelled and their acceptance credentials are cleared', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    $pending = Invitation::factory()->forOrganization($organization)->pending()->create();

    $cancelled = app(CancelInvitationAction::class)->handle($owner, $organization, $pending);

    expect($cancelled->status)->toBe(InvitationStatus::Cancelled)
        ->and($cancelled->invite_token_hash)->toBeNull()
        ->and($cancelled->invite_code_hash)->toBeNull()
        ->and($cancelled->accepted_by_user_id)->toBeNull()
        ->and($cancelled->accepted_at)->toBeNull()
        ->and($cancelled->canBeAccepted())->toBeFalse();

    $auditLog = AuditLog::query()->latest('id')->firstOrFail();
    $auditPayload = json_encode([$auditLog->old_values, $auditLog->new_values], JSON_THROW_ON_ERROR);

    expect($auditLog->action)->toBe(AuditLogAction::InvitationCancelled)
        ->and($auditPayload)->not->toContain('invite_token')
        ->and($auditPayload)->not->toContain('invite_code')
        ->and($auditPayload)->not->toContain('hash');
});

test('pending invitation credentials can be securely reissued without persisting plaintext', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    $waiter = roleForLifecycle(SystemRole::Waiter);
    $created = app(CreateInvitationAction::class)->handle($organization, $waiter, $owner, [
        'email' => 'reissue@example.test',
        'invite_token' => str_repeat('O', 64),
        'invite_code' => 'OLDCODE1',
        'expires_at' => now()->addMinute(),
    ]);

    $reissued = app(ReissueInvitationAction::class)->handle(
        $owner,
        $organization,
        $created->invitation,
    );

    expect($reissued->token)->toHaveLength(64)
        ->and($reissued->token)->not->toBe($created->token)
        ->and($reissued->code)->toHaveLength(8)
        ->and($reissued->invitation->status)->toBe(InvitationStatus::Pending)
        ->and($reissued->invitation->invite_token_hash)->toBe(hash('sha256', $reissued->token))
        ->and($reissued->invitation->invite_code_hash)->toBe(hash('sha256', $reissued->code))
        ->and($reissued->invitation->expires_at->isAfter(now()->addDays(6)))->toBeTrue();

    $this->get(route('invitations.show', ['token' => $created->token]))
        ->assertRedirect(route('invitations.pending'));
    $this->get(route('invitations.pending'))
        ->assertGone()
        ->assertSee(__('invitations.states.unavailable_title'));
    $this->get(route('invitations.show', ['token' => $reissued->token]))
        ->assertRedirect(route('invitations.pending'));

    expect(AuditLog::query()
        ->where('action', AuditLogAction::InvitationReissued->value)
        ->where('entity_id', $created->invitation->id)
        ->count())->toBe(1);
});

test('reissuing an invitation fails closed for an inconsistent brand scope', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    [, $otherOrganization] = createOrganizationRoleLifecycleContext();
    $otherBrand = Brand::factory()->for($otherOrganization)->create();
    $invitation = Invitation::factory()
        ->forOrganization($organization)
        ->pending()
        ->create(['brand_id' => $otherBrand->id]);
    $originalHash = $invitation->invite_token_hash;

    expect(fn () => app(ReissueInvitationAction::class)->handle($owner, $organization, $invitation))
        ->toThrow(ModelNotFoundException::class);

    expect($invitation->fresh()->invite_token_hash)->toBe($originalHash)
        ->and(AuditLog::query()
            ->where('action', AuditLogAction::InvitationReissued->value)
            ->where('entity_id', $invitation->id)
            ->exists())->toBeFalse();
});

test('invitation cancellation rejects an identifier from another organization', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    [, $otherOrganization] = createOrganizationRoleLifecycleContext();
    $foreignInvitation = Invitation::factory()->forOrganization($otherOrganization)->pending()->create();

    expect(fn () => app(CancelInvitationAction::class)->handle($owner, $organization, $foreignInvitation))
        ->toThrow(ModelNotFoundException::class);

    expect($foreignInvitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('accepted expired and already cancelled invitations are immutable', function (string $state) {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    $invitation = Invitation::factory()->forOrganization($organization)->{$state}()->create();
    $originalStatus = $invitation->status;

    expect(fn () => app(CancelInvitationAction::class)->handle($owner, $organization, $invitation))
        ->toThrow(ValidationException::class);

    expect($invitation->fresh()->status)->toBe($originalStatus);
})->with(['acceptedBy', 'expired', 'cancelled']);

test('critical role changes require an audit reason', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    $waiter = roleForLifecycle(SystemRole::Waiter);
    $director = roleForLifecycle(SystemRole::Director);
    $membership = OrganizationUser::factory()->forOrganization($organization)->forRole($waiter)->create();

    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle(
        $owner,
        $organization,
        $membership,
        $director,
        ' ',
    ))->toThrow(ValidationException::class);

    expect($membership->fresh()->role_id)->toBe($waiter->id)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('staff pages update roles and cancel pending invitations', function () {
    [$owner, $organization] = createOrganizationRoleLifecycleContext();
    [$brand, $branch] = createBranchRoleLifecycleContext($organization);
    $waiter = roleForLifecycle(SystemRole::Waiter);
    $director = roleForLifecycle(SystemRole::Director);
    $staffUser = User::factory()->create();
    $organizationMembership = OrganizationUser::factory()->forOrganization($organization)->forUser($staffUser)->forRole($waiter)->create();
    $branchMembership = BranchUser::factory()->forBranch($branch)->forUser($staffUser)->forRole($waiter)->create();
    $organizationInvitation = Invitation::factory()->forOrganization($organization)->pending()->create();
    $branchInvitation = Invitation::factory()->forOrganization($organization)->pending()->create([
        'brand_id' => $brand->id,
        'branch_id' => $branch->id,
    ]);

    Livewire::actingAs($owner)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->call('startEditingRole', $organizationMembership->id)
        ->set('editingRoleId', $director->id)
        ->set('staffRoleReason', 'Organization role changed after promotion.')
        ->call('updateRole')
        ->call('cancelInvitation', $organizationInvitation->id)
        ->assertHasNoErrors();

    Livewire::actingAs($owner)
        ->test(BranchStaffIndex::class, compact('organization', 'brand', 'branch'))
        ->call('startEditingRole', $branchMembership->id)
        ->set('editingRoleId', $director->id)
        ->set('staffRoleReason', 'Branch duties changed after promotion.')
        ->call('updateRole')
        ->call('cancelInvitation', $branchInvitation->id)
        ->assertHasNoErrors();

    expect($organizationMembership->fresh()->role_id)->toBe($director->id)
        ->and($branchMembership->fresh()->role_id)->toBe($director->id)
        ->and($organizationInvitation->fresh()->status)->toBe(InvitationStatus::Cancelled)
        ->and($branchInvitation->fresh()->status)->toBe(InvitationStatus::Cancelled);
});

function createOrganizationRoleLifecycleContext(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, [
        'name' => fake()->unique()->company().' Group',
    ]);

    return [$owner->fresh(), $organization];
}

function createBranchRoleLifecycleContext(Organization $organization): array
{
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();

    return [$brand, $branch];
}

function roleForLifecycle(SystemRole $role): Role
{
    return Role::query()->where('code', $role->value)->firstOrFail();
}
