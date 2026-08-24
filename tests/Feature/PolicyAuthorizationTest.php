<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('organization policy separates owner member and outsider capabilities', function (): void {
    [$organization, , , $owner] = policyOrganizationContext();
    $member = attachPolicyUser($organization, SystemRole::Waiter);
    $outsider = User::factory()->create();

    expect(Gate::forUser($owner)->allows('view', $organization))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $organization))->toBeTrue()
        ->and(Gate::forUser($member)->allows('view', $organization))->toBeTrue()
        ->and(Gate::forUser($member)->allows('update', $organization))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('view', $organization))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('update', $organization))->toBeFalse();
});

test('director can manage brands and branches without becoming organization owner', function (): void {
    [$organization, $brand, $branch] = policyOrganizationContext();
    $director = attachPolicyUser($organization, SystemRole::Director);

    expect(Gate::forUser($director)->allows('manageBrands', $organization))->toBeTrue()
        ->and(Gate::forUser($director)->allows('create', [Brand::class, $organization]))->toBeTrue()
        ->and(Gate::forUser($director)->allows('create', [Branch::class, $organization]))->toBeTrue()
        ->and(Gate::forUser($director)->allows('update', $brand))->toBeTrue()
        ->and(Gate::forUser($director)->allows('update', $branch))->toBeTrue()
        ->and(Gate::forUser($director)->allows('update', $organization))->toBeFalse();
});

test('branch policy applies resource permissions only inside an accessible organization', function (): void {
    [$organization, , $branch] = policyOrganizationContext();
    [, , $foreignBranch] = policyOrganizationContext('Foreign policy organization');
    $headChef = attachPolicyUser($organization, SystemRole::HeadChef);

    expect(Gate::forUser($headChef)->allows('view', $branch))->toBeTrue()
        ->and(Gate::forUser($headChef)->allows('changeMenuAvailability', $branch))->toBeTrue()
        ->and(Gate::forUser($headChef)->allows('manageMenu', $branch))->toBeFalse()
        ->and(Gate::forUser($headChef)->allows('view', $foreignBranch))->toBeFalse()
        ->and(Gate::forUser($headChef)->allows('changeMenuAvailability', $foreignBranch))->toBeFalse();
});

test('waiter may change table status but may not manage service point definitions', function (): void {
    [$organization, , $branch] = policyOrganizationContext();
    $waiter = attachPolicyUser($organization, SystemRole::Waiter);

    expect(Gate::forUser($waiter)->allows('changeServicePointStatus', $branch))->toBeTrue()
        ->and(Gate::forUser($waiter)->allows('manageServicePoints', $branch))->toBeFalse()
        ->and(Gate::forUser($waiter)->allows('openTable', $branch))->toBeTrue();
});

test('inactive organization membership denies all branch capabilities', function (): void {
    [$organization, , $branch] = policyOrganizationContext();
    $inactiveUser = attachPolicyUser($organization, SystemRole::Director, OrganizationUserStatus::Suspended);

    expect(Gate::forUser($inactiveUser)->allows('view', $branch))->toBeFalse()
        ->and(Gate::forUser($inactiveUser)->allows('update', $branch))->toBeFalse()
        ->and(Gate::forUser($inactiveUser)->allows('manageMenu', $branch))->toBeFalse();
});

test('role assignment follows the actor hierarchy and never grants superadmin', function (): void {
    [$organization] = policyOrganizationContext();
    $director = attachPolicyUser($organization, SystemRole::Director);
    $restaurantAdmin = attachPolicyUser($organization, SystemRole::RestaurantAdmin);
    $ownerRole = Role::query()->where('code', SystemRole::Owner->value)->firstOrFail();
    $directorRole = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();
    $restaurantAdminRole = Role::query()->where('code', SystemRole::RestaurantAdmin->value)->firstOrFail();
    $headChefRole = Role::query()->where('code', SystemRole::HeadChef->value)->firstOrFail();
    $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $superadminRole = Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail();

    expect(Gate::forUser($director)->allows('assign', [$restaurantAdminRole, $organization]))->toBeTrue()
        ->and(Gate::forUser($director)->allows('assign', [$headChefRole, $organization]))->toBeTrue()
        ->and(Gate::forUser($director)->allows('assign', [$waiterRole, $organization]))->toBeTrue()
        ->and(Gate::forUser($director)->allows('assign', [$directorRole, $organization]))->toBeFalse()
        ->and(Gate::forUser($director)->allows('assign', [$ownerRole, $organization]))->toBeFalse()
        ->and(Gate::forUser($director)->allows('assign', [$superadminRole, $organization]))->toBeFalse()
        ->and(Gate::forUser($restaurantAdmin)->allows('assign', [$headChefRole, $organization]))->toBeTrue()
        ->and(Gate::forUser($restaurantAdmin)->allows('assign', [$directorRole, $organization]))->toBeFalse();
});

test('invitation policy requires an explicit matching recipient email', function (): void {
    [$organization] = policyOrganizationContext();
    $recipient = User::factory()->create(['email' => 'recipient@example.test']);
    $matching = Invitation::factory()->forOrganization($organization)->pending()->create([
        'email' => 'recipient@example.test',
    ]);
    $unbound = Invitation::factory()->forOrganization($organization)->pending()->create([
        'email' => null,
    ]);

    expect(Gate::forUser($recipient)->allows('accept', $matching))->toBeTrue()
        ->and(Gate::forUser($recipient)->allows('accept', $unbound))->toBeFalse();
});

test('permission management cannot target an equal or higher tenant role', function (): void {
    [$organization] = policyOrganizationContext();
    $restaurantAdmin = attachPolicyUser($organization, SystemRole::RestaurantAdmin);
    $director = attachPolicyUser($organization, SystemRole::Director);
    $waiter = attachPolicyUser($organization, SystemRole::Waiter);
    $directorMembership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $director->id)
        ->firstOrFail();
    $waiterMembership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $waiter->id)
        ->firstOrFail();

    expect(Gate::forUser($restaurantAdmin)->allows('managePermissions', $waiterMembership))->toBeTrue()
        ->and(Gate::forUser($restaurantAdmin)->allows('managePermissions', $directorMembership))->toBeFalse();
});

/**
 * @return array{Organization, Brand, Branch, User}
 */
function policyOrganizationContext(string $name = 'Policy organization'): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => $name]);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();

    return [$organization, $brand, $branch, $owner->fresh()];
}

function attachPolicyUser(
    Organization $organization,
    SystemRole $systemRole,
    OrganizationUserStatus $status = OrganizationUserStatus::Active,
): User {
    $user = User::factory()->create();
    $role = Role::query()->where('code', $systemRole->value)->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => $status->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $user->fresh();
}
