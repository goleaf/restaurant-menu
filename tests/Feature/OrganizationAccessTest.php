<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('organization users table has membership fields', function () {
    expect(Schema::hasTable('organization_users'))->toBeTrue();
    expect(Schema::hasColumns('organization_users', [
        'user_id',
        'organization_id',
        'role_id',
        'status',
        'joined_at',
        'invited_by_user_id',
    ]))->toBeTrue();
});

test('creating organization creates active owner membership', function () {
    $user = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($user, ['name' => 'Owner Context']);
    $ownerRole = Role::query()
        ->where('code', SystemRole::Owner->value)
        ->firstOrFail();

    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($membership->role_id)->toBe($ownerRole->id);
    expect($membership->status)->toBe(OrganizationUserStatus::Active);
    expect($membership->joined_at)->not->toBeNull();
    expect($membership->invited_by_user_id)->toBeNull();
    expect($user->fresh()->canAccessOrganization($organization))->toBeTrue();
});

test('organization can have multiple active directors', function () {
    $owner = User::factory()->create();
    $firstDirector = User::factory()->create();
    $secondDirector = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Directors Group']);
    $directorRole = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $firstDirector->id => [
            'role_id' => $directorRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
        $secondDirector->id => [
            'role_id' => $directorRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);

    $directorCount = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('role_id', $directorRole->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->count();

    expect($directorCount)->toBe(2);
    expect($firstDirector->fresh()->hasOrganizationRole($organization, SystemRole::Director))->toBeTrue();
    expect($secondDirector->fresh()->hasOrganizationRole($organization, SystemRole::Director))->toBeTrue();
});

test('permissions are checked in organization context', function () {
    $user = User::factory()->create();
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $firstOrganization = (new CreateOrganizationAction)->handle($firstOwner, ['name' => 'Allowed Org']);
    $secondOrganization = (new CreateOrganizationAction)->handle($secondOwner, ['name' => 'Denied Org']);
    $directorRole = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $permission = Permission::query()
        ->where('code', SystemPermission::ManageBranches->value)
        ->firstOrFail();

    $directorRole->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);

    $firstOrganization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $directorRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);
    $secondOrganization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);

    expect($user->fresh()->hasPermission(SystemPermission::ManageBranches, $firstOrganization))->toBeTrue();
    expect($user->fresh()->hasPermission(SystemPermission::ManageBranches, $secondOrganization))->toBeFalse();
});

test('only active memberships grant organization access', function (OrganizationUserStatus $status, bool $expectedAccess) {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Status Context']);
    $directorRole = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $member->id => [
            'role_id' => $directorRole->id,
            'status' => $status->value,
            'joined_at' => $status === OrganizationUserStatus::Active ? now() : null,
        ],
    ]);

    expect($member->fresh()->canAccessOrganization($organization))->toBe($expectedAccess);
})->with([
    'active' => [OrganizationUserStatus::Active, true],
    'invited' => [OrganizationUserStatus::Invited, false],
    'suspended' => [OrganizationUserStatus::Suspended, false],
    'removed' => [OrganizationUserStatus::Removed, false],
]);
