<?php

use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as BranchStaffIndex;
use App\Livewire\Organizations\Staff\Index as OrganizationStaffIndex;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('branch users table stores branch staff assignments', function () {
    expect(Schema::hasTable('branch_users'))->toBeTrue();
    expect(Schema::hasColumns('branch_users', [
        'organization_id',
        'branch_id',
        'user_id',
        'role_id',
        'status',
        'assigned_at',
        'assigned_by_user_id',
    ]))->toBeTrue();
});

test('organization staff page requires manage staff permission', function () {
    [$manager, $organization] = createOrganizationForStaff();

    $this->actingAs($manager)
        ->get(route('organizations.staff.index', $organization))
        ->assertForbidden();

    grantManageStaff($manager, $organization);

    $this->actingAs($manager)
        ->get(route('organizations.staff.index', $organization))
        ->assertOk()
        ->assertSee(__('staff.organization_access'));
});

test('branch staff page requires manage staff permission', function () {
    [$manager, $organization] = createOrganizationForStaff();
    [$brand, $branch] = createBranchForStaff($organization);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.staff.index', [$organization, $brand, $branch]))
        ->assertForbidden();

    grantManageStaff($manager, $organization);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.staff.index', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSee(__('staff.branch_access'));
});

test('organization staff page changes existing access only after a reason and preview', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $staffUser = User::factory()->create(['name' => 'Existing Waiter']);
    $membership = OrganizationUser::factory()->forOrganization($organization)->forUser($staffUser)->forRole($role)->active()->create();
    $component = Livewire::actingAs($manager)->test(OrganizationStaffIndex::class, ['organization' => $organization]);
    foreach (['suspended', 'active'] as $status) {
        $component->call('openMember', $membership->id, 'status')->set('memberForm.status', $status)
            ->set('memberForm.reason', 'Documented access change for this colleague.')
            ->call('previewMemberChange')->call('saveMember')->assertHasNoErrors();
        expect($membership->fresh()->status->value)->toBe($status);
    }
});

test('organization staff page previews and creates a response only invitation link', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->call('openInvitation')
        ->set('invitationForm.email', 'invited-waiter@example.test')
        ->set('invitationForm.phone', '+37060000001')
        ->set('invitationForm.roleId', $role->id)
        ->call('previewInvitation')
        ->call('createInviteLink')
        ->assertSee(__('staff.link.label'))
        ->assertDontSee(__('staff.invite_code'));

    $invitation = Invitation::query()
        ->where('organization_id', $organization->id)
        ->where('email', 'invited-waiter@example.test')
        ->latest('id')
        ->firstOrFail();

    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->phone)->toBe('+37060000001');
    expect($invitation->invite_token_hash)->toHaveLength(64);
    expect($invitation->invite_code_hash)->toHaveLength(64);
});

test('branch staff page assigns an accepted colleague and changes only branch access', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    [$brand, $branch] = createBranchForStaff($organization);
    $organizationRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $role = Role::query()->where('code', SystemRole::Bartender->value)->firstOrFail();
    $staffUser = User::factory()->create(['name' => 'Existing Staff Member', 'email' => 'existing-staff@example.test']);
    $organizationMembership = OrganizationUser::factory()->forOrganization($organization)->forUser($staffUser)->forRole($organizationRole)->active()->create();
    $component = Livewire::actingAs($manager)->test(BranchStaffIndex::class, compact('organization', 'brand', 'branch'))
        ->call('openExistingAssignment')->set('memberForm.organizationMemberId', $organizationMembership->id)
        ->set('memberForm.roleId', $role->id)->call('assignExistingMember')->assertHasNoErrors()->assertSee('Existing Staff Member');
    $branchUser = BranchUser::query()->where('branch_id', $branch->id)->where('user_id', $staffUser->id)->firstOrFail();
    expect($organizationMembership->fresh()->role_id)->toBe($organizationRole->id)->and($branchUser->role_id)->toBe($role->id);
    foreach (['suspended', 'active'] as $status) {
        $component->call('openMember', $branchUser->id, 'status')->set('memberForm.status', $status)
            ->set('memberForm.reason', 'Documented access change for this branch.')
            ->call('previewMemberChange')->call('saveMember')->assertHasNoErrors();
        expect($branchUser->fresh()->status->value)->toBe($status)
            ->and($organizationMembership->fresh()->status)->toBe(OrganizationUserStatus::Active);
    }
});

test('branch staff page excludes memberships with an inconsistent organization', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    [$brand, $branch] = createBranchForStaff($organization);
    [, $otherOrganization] = createOrganizationForStaff();
    $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $foreignUser = User::factory()->create(['email' => 'foreign-branch-user@example.test']);
    $inconsistentMembership = BranchUser::factory()
        ->forBranch($branch)
        ->forUser($foreignUser)
        ->forRole($waiterRole)
        ->create(['organization_id' => $otherOrganization->id]);

    $component = Livewire::actingAs($manager)
        ->test(BranchStaffIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertDontSee('foreign-branch-user@example.test');

    expect(fn () => $component->call('openMember', $inconsistentMembership->id, 'status'))
        ->toThrow(ModelNotFoundException::class);
});

test('branch staff page previews and creates a branch scoped invitation link', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    [$brand, $branch] = createBranchForStaff($organization);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(BranchStaffIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('openInvitation')
        ->set('invitationForm.email', 'branch-invite@example.test')
        ->set('invitationForm.phone', '+37060000002')
        ->set('invitationForm.roleId', $role->id)
        ->call('previewInvitation')
        ->call('createInviteLink')
        ->assertSee(__('staff.link.label'))
        ->assertDontSee(__('staff.invite_code'));

    $invitation = Invitation::query()
        ->where('organization_id', $organization->id)
        ->where('brand_id', $brand->id)
        ->where('branch_id', $branch->id)
        ->where('email', 'branch-invite@example.test')
        ->latest('id')
        ->firstOrFail();

    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->phone)->toBe('+37060000002');
    expect($invitation->invite_token_hash)->toHaveLength(64);
    expect($invitation->invite_code_hash)->toHaveLength(64);
});

test('organization staff page reissues a scoped invitation and rejects a foreign identifier', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    [, $otherOrganization] = createOrganizationForStaff();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $invitation = app(CreateInvitationAction::class)->handle(
        $organization,
        $role,
        $manager,
        [
            'email' => 'reissue-ui@example.test',
            'invite_token' => str_repeat('L', 64),
            'invite_code' => 'LINKCODE',
        ],
    )->invitation;
    $foreignInvitation = Invitation::factory()->forOrganization($otherOrganization)->pending()->create();
    $oldHash = $invitation->invite_token_hash;

    Livewire::actingAs($manager)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->call('confirmInvitation', $invitation->id, 'reissue')
        ->call('reissueInvitation', $invitation->id)
        ->assertHasNoErrors()
        ->assertViewHas('createdInvitationLink', fn (?string $link): bool => is_string($link) && $link !== '');

    expect(fn () => Livewire::actingAs($manager)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->call('confirmInvitation', $foreignInvitation->id, 'reissue'))
        ->toThrow(ModelNotFoundException::class);

    expect($invitation->fresh()->invite_token_hash)->not->toBe($oldHash)
        ->and($foreignInvitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('branch staff page excludes invitations with an inconsistent brand', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    [$brand, $branch] = createBranchForStaff($organization);
    $otherBrand = Brand::factory()->for($organization)->create();
    $inconsistentInvitation = Invitation::factory()
        ->forOrganization($organization)
        ->pending()
        ->create([
            'brand_id' => $otherBrand->id,
            'branch_id' => $branch->id,
            'email' => 'inconsistent-brand@example.test',
        ]);

    $component = Livewire::actingAs($manager)
        ->test(BranchStaffIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertDontSee('inconsistent-brand@example.test');

    expect(fn () => $component->call('confirmInvitation', $inconsistentInvitation->id, 'reissue'))
        ->toThrow(ModelNotFoundException::class);
});

test('restaurant administrator cannot discover or submit a higher invitation role through livewire', function () {
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Role Boundary Group']);
    $restaurantAdmin = User::factory()->create();
    $restaurantAdminRole = Role::query()->where('code', SystemRole::RestaurantAdmin->value)->firstOrFail();
    $directorRole = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();
    $restaurantAdmin->roles()->sync([$restaurantAdminRole->id]);
    OrganizationUser::factory()
        ->forOrganization($organization)
        ->forUser($restaurantAdmin)
        ->forRole($restaurantAdminRole)
        ->active()
        ->create();

    Livewire::actingAs($restaurantAdmin)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->assertViewHas('roleOptions', fn (array $roles): bool => ! in_array($directorRole->id, array_column($roles, 'id'), true))
        ->call('openInvitation')
        ->set('invitationForm.email', 'privilege-escalation@example.test')
        ->set('invitationForm.roleId', $directorRole->id)
        ->call('previewInvitation')
        ->call('createInviteLink')
        ->assertHasErrors('invitationForm.roleId');

    expect(Invitation::query()
        ->where('organization_id', $organization->id)
        ->where('email', 'privilege-escalation@example.test')
        ->exists())->toBeFalse();
});

function createOrganizationForStaff(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Staff Group']);
    $role = Role::query()->where('code', SystemRole::ShiftManager->value)->firstOrFail();

    $manager->roles()->sync([$role->id]);
    OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail()
        ->forceFill(['role_id' => $role->id])
        ->save();

    return [$manager->fresh(), $organization];
}

function createBranchForStaff(Organization $organization): array
{
    $brand = Brand::factory()->for($organization)->create(['name' => 'Staff Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Staff Branch']);

    return [$brand, $branch];
}

function grantManageStaff(User $user, Organization $organization): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
    $permission = Permission::query()
        ->where('code', SystemPermission::ManageStaff->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
}
