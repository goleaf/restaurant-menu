<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Staff\SetBranchStaffStatusAction;
use App\Actions\Staff\SetOrganizationStaffStatusAction;
use App\Actions\Staff\SetUserPermissionOverrideAction;
use App\Actions\Staff\SyncWaiterAreaAssignmentsAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('organization staff lifecycle action rejects an unauthorized direct caller', function (): void {
    [, $organization] = staffActionOrganization();
    $waiterRole = staffActionRole(SystemRole::Waiter);
    $membership = OrganizationUser::factory()
        ->forOrganization($organization)
        ->forRole($waiterRole)
        ->active()
        ->create();
    $outsider = User::factory()->create();

    expect(fn () => app(SetOrganizationStaffStatusAction::class)->suspend(
        $membership,
        $outsider,
        'Unauthorized suspension attempt.',
    ))->toThrow(AuthorizationException::class);

    expect($membership->fresh()->status)->toBe(OrganizationUserStatus::Active);
});

test('branch lifecycle and waiter area actions reject an unauthorized direct caller', function (): void {
    [, $organization] = staffActionOrganization();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $area = AreaNode::factory()->for($branch)->create();
    $waiterRole = staffActionRole(SystemRole::Waiter);
    $waiter = User::factory()->create();
    $membership = BranchUser::factory()
        ->forBranch($branch)
        ->forUser($waiter)
        ->forRole($waiterRole)
        ->active()
        ->create();
    $outsider = User::factory()->create();

    expect(fn () => app(SetBranchStaffStatusAction::class)->suspend(
        $membership,
        $outsider,
        'Unauthorized suspension attempt.',
    ))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SyncWaiterAreaAssignmentsAction::class)->handle(
            $branch,
            $membership,
            $outsider,
            [$area->id],
        ))->toThrow(AuthorizationException::class);

    expect($membership->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and(AreaNodeWaiter::query()->where('area_node_id', $area->id)->exists())->toBeFalse();
});

test('waiter area action rejects a membership with an inconsistent organization', function (): void {
    [$owner, $organization] = staffActionOrganization();
    [, $otherOrganization] = staffActionOrganization();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $area = AreaNode::factory()->for($branch)->create();
    $waiterRole = staffActionRole(SystemRole::Waiter);
    $membership = BranchUser::factory()
        ->forBranch($branch)
        ->forRole($waiterRole)
        ->active()
        ->create(['organization_id' => $otherOrganization->id]);

    expect(fn () => app(SyncWaiterAreaAssignmentsAction::class)->handle(
        $branch,
        $membership,
        $owner,
        [$area->id],
    ))->toThrow(InvalidArgumentException::class);

    expect(AreaNodeWaiter::query()->where('area_node_id', $area->id)->exists())->toBeFalse();
});

test('permission override action rejects a cross tenant direct caller', function (): void {
    [, $organization] = staffActionOrganization();
    $target = User::factory()->create();
    OrganizationUser::factory()
        ->forOrganization($organization)
        ->forUser($target)
        ->forRole(staffActionRole(SystemRole::Waiter))
        ->active()
        ->create();
    $permission = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();
    $outsider = User::factory()->create();

    expect(fn () => app(SetUserPermissionOverrideAction::class)->handle(
        $target,
        $permission,
        PermissionOverrideState::Allow,
        $outsider,
        $organization->id,
        'Unauthorized override attempt.',
    ))->toThrow(AuthorizationException::class);

    expect($target->permissionOverrides()->whereKey($permission->id)->exists())->toBeFalse();
});

/** @return array{User, Organization} */
function staffActionOrganization(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, [
        'name' => fake()->unique()->company().' Staff Action Group',
    ]);

    return [$owner->fresh(), $organization];
}

function staffActionRole(SystemRole $role): Role
{
    return Role::query()->where('code', $role->value)->firstOrFail();
}
