<?php

declare(strict_types=1);

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Policies\BranchPolicy;
use Illuminate\Database\Eloquent\Collection;

test('single branch access does not hydrate organization assignments', function (int $count, bool $assigned): void {
    [$user, $organization, $branches] = branchAccessQueryFixture($count, OrganizationUserStatus::Active);
    $branch = $assigned ? $branches->firstOrFail() : Branch::factory()->create([
        'organization_id' => $organization->id,
        'brand_id' => $branches->firstOrFail()->brand_id,
        'name' => 'Unassigned branch',
    ]);
    $hydratedAssignments = 0;
    BranchUser::retrieved(function () use (&$hydratedAssignments): void {
        $hydratedAssignments++;
    });
    $allowed = null;

    $queries = countDatabaseQueries(function () use ($user, $branch, $organization, &$allowed): void {
        $allowed = $user->canAccessBranch($branch, $organization);
    });

    expect($allowed)->toBe($assigned)
        ->and($queries)->toBe(5)
        ->and($hydratedAssignments)->toBe(0);
})->with([1, 40, 400])->with(['assigned branch' => true, 'unassigned branch' => false]);

test('organization wide branch access uses a single existence lookup after membership checks', function (int $count): void {
    [$user, $organization, $branches] = branchAccessQueryFixture($count);
    $allowed = null;

    $queries = countDatabaseQueries(function () use ($user, $organization, $branches, &$allowed): void {
        $allowed = $user->canAccessBranch($branches->firstOrFail(), $organization);
    });

    expect($allowed)->toBeTrue()
        ->and($queries)->toBe(5);
})->with([1, 40, 400]);

test('every assignment status restricts fallback and only active assignments allow access', function (OrganizationUserStatus $status): void {
    [$user, $organization, $branches] = branchAccessQueryFixture(1, $status);

    expect($user->canAccessBranch($branches->firstOrFail(), $organization))
        ->toBe($status === OrganizationUserStatus::Active);
})->with(OrganizationUserStatus::cases());

test('an active branch assignment still requires active organization membership', function (OrganizationUserStatus $status): void {
    [$user, $organization, $branches, , $membership] = branchAccessQueryFixture(1, OrganizationUserStatus::Active);
    $membership->forceFill(['status' => $status])->save();
    OrganizationSubscription::factory()->for($organization)->active()->create();

    expect($user->canAccessBranch($branches->firstOrFail(), $organization))
        ->toBe($status === OrganizationUserStatus::Active);
})->with(OrganizationUserStatus::cases());

test('branch access rejects missing membership even with an active assignment', function (): void {
    [$user, $organization, $branches, , $membership] = branchAccessQueryFixture(1, OrganizationUserStatus::Active);
    $membership->delete();

    expect($user->canAccessBranch($branches->firstOrFail(), $organization))->toBeFalse();
});

test('inactive subscription denies both assigned and organization wide branch access', function (bool $assigned): void {
    [$user, $organization, $branches] = branchAccessQueryFixture(1, $assigned ? OrganizationUserStatus::Active : null);
    OrganizationSubscription::factory()->for($organization)->inactive()->create();

    expect($user->canAccessBranch($branches->firstOrFail(), $organization))->toBeFalse();
})->with([true, false]);

test('assignments in another organization or for another user do not restrict fallback', function (): void {
    [$user, $organization, $branches, $role] = branchAccessQueryFixture();
    $foreignBranch = Branch::factory()->create();
    BranchUser::factory()->forBranch($foreignBranch)->forUser($user)->forRole($role)->create();
    BranchUser::factory()->forBranch($branches->firstOrFail())->forRole($role)->create();

    expect($user->canAccessBranch($branches->firstOrFail(), $organization))->toBeTrue()
        ->and($user->canAccessBranch($foreignBranch))->toBeFalse();
});

test('branch access accepts scoped identifiers and rejects missing or wrong parent branches', function (): void {
    [$user, $organization, $branches] = branchAccessQueryFixture(1, OrganizationUserStatus::Active);
    $foreignOrganization = Organization::factory()->create();
    $branch = $branches->firstOrFail();

    expect($user->canAccessBranch($branch->id, $organization->id))->toBeTrue()
        ->and($user->canAccessBranch($branch, $foreignOrganization))->toBeFalse()
        ->and($user->canAccessBranch($branch->id, $foreignOrganization->id))->toBeFalse()
        ->and($user->canAccessBranch(PHP_INT_MAX, $organization))->toBeFalse();
});

test('organization wide branch access preserves the explicit archived branch option', function (): void {
    [$user, $organization, $branches] = branchAccessQueryFixture();
    $branch = $branches->firstOrFail();
    $branch->delete();

    expect($user->canAccessBranch($branch, $organization))->toBeFalse()
        ->and($user->canAccessBranch($branch, $organization, true))->toBeTrue()
        ->and($user->canAccessBranch($branch->id, $organization))->toBeFalse()
        ->and($user->canAccessBranch($branch->id, $organization, true))->toBeTrue();
});

test('assigned archived branch models retain model access while identifiers and view policy remain scoped', function (): void {
    [$user, $organization, $branches] = branchAccessQueryFixture(1, OrganizationUserStatus::Active);
    $branch = $branches->firstOrFail();
    $branch->delete();

    expect($user->canAccessBranch($branch, $organization))->toBeTrue()
        ->and($user->canAccessBranch($branch->id, $organization))->toBeFalse()
        ->and($user->canAccessBranch($branch, $organization, true))->toBeTrue()
        ->and($user->canAccessBranch($branch->id, $organization, true))->toBeTrue()
        ->and(app(BranchPolicy::class)->view($user, $branch))->toBeFalse();
});

test('superadmin bypass preserves tenant scope and archived branch handling', function (): void {
    [$user, $organization, $branches, , $membership] = branchAccessQueryFixture(1, OrganizationUserStatus::Removed);
    $superadminRole = Role::factory()->forSystemRole(SystemRole::Superadmin)->create();
    $user->roles()->attach($superadminRole);
    $membership->delete();
    OrganizationSubscription::factory()->for($organization)->inactive()->create();
    $branch = $branches->firstOrFail();
    $foreignOrganization = Organization::factory()->create();

    expect($user->canAccessBranch($branch, $organization))->toBeTrue()
        ->and($user->canAccessBranch($branch, $foreignOrganization))->toBeFalse();

    $branch->delete();

    expect($user->canAccessBranch($branch, $organization))->toBeFalse()
        ->and($user->canAccessBranch($branch, $organization, true))->toBeTrue();
});

/**
 * @return array{User, Organization, Collection<int, Branch>, Role, OrganizationUser}
 */
function branchAccessQueryFixture(int $branchCount = 1, ?OrganizationUserStatus $assignmentStatus = null): array
{
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $user = User::factory()->create();
    $role = Role::factory()->forSystemRole(SystemRole::Waiter)->create();
    $membership = OrganizationUser::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);
    $branches = Branch::factory()->count($branchCount)->for($organization)->for($brand)
        ->sequence(fn ($sequence): array => ['name' => 'Access branch '.$sequence->index])
        ->create();

    if ($assignmentStatus !== null) {
        BranchUser::factory()->count($branchCount)
            ->sequence(fn ($sequence): array => ['branch_id' => $branches[$sequence->index]->id])
            ->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'assigned_by_user_id' => $user->id,
                'status' => $assignmentStatus,
            ]);
    }

    return [$user, $organization, $branches, $role, $membership];
}
