<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Payments\ResolvePaymentAccessibleBranchIdsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\Menu\Index as MenuIndex;
use App\Livewire\Organizations\Index as OrganizationsIndex;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('ordinary users only see their own organizations', function () {
    [$visibleOrganization, $visibleBrand, $visibleBranch, $visibleOwner] = prompt96RestaurantContext(
        organizationName: 'Prompt 96 Visible Group',
        brandName: 'Prompt 96 Visible Brand',
        branchName: 'Prompt 96 Visible Branch',
    );
    [$hiddenOrganization] = prompt96RestaurantContext(
        organizationName: 'Prompt 96 Hidden Group',
        brandName: 'Prompt 96 Hidden Brand',
        branchName: 'Prompt 96 Hidden Branch',
    );

    Livewire::actingAs($visibleOwner)
        ->test(OrganizationsIndex::class)
        ->assertSee($visibleOrganization->name)
        ->assertDontSee($hiddenOrganization->name);

    $this->actingAs($visibleOwner)
        ->get(route('organizations.brands.index', $visibleOrganization))
        ->assertOk()
        ->assertSee($visibleBrand->name);

    $this->actingAs($visibleOwner)
        ->get(route('organizations.brands.branches.menu.index', [$visibleOrganization, $visibleBrand, $visibleBranch]))
        ->assertOk();

    $this->actingAs($visibleOwner)
        ->get(route('organizations.brands.index', $hiddenOrganization))
        ->assertForbidden();
});

test('branch assigned employees cannot see or open another branch without access', function () {
    [$organization, $brand, $assignedBranch] = prompt96RestaurantContext(
        organizationName: 'Prompt 96 Branch Scope Group',
        brandName: 'Prompt 96 Branch Scope Brand',
        branchName: 'Prompt 96 Assigned Branch',
    );
    $hiddenBranch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Prompt 96 Hidden Branch']);
    $employee = User::factory()->create(['name' => 'Prompt 96 Branch Employee']);
    $role = prompt96AttachUserToOrganization($employee, $organization, SystemRole::RestaurantAdmin);

    prompt96GrantRolePermissions($role, [SystemPermission::ManageMenu]);
    prompt96AssignUserToBranch($employee, $organization, $assignedBranch, $role);

    Livewire::actingAs($employee->fresh())
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee('Prompt 96 Assigned Branch')
        ->assertDontSee('Prompt 96 Hidden Branch');

    Livewire::actingAs($employee->fresh())
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $assignedBranch])
        ->assertOk()
        ->assertSet('branch.id', $assignedBranch->id);

    Livewire::actingAs($employee->fresh())
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $hiddenBranch])
        ->assertForbidden();
});

test('waiter can manage menu labels without changing prices when change prices is denied', function () {
    [$organization, $brand, $branch] = prompt96RestaurantContext(
        organizationName: 'Prompt 96 Waiter Price Group',
        brandName: 'Prompt 96 Waiter Price Brand',
        branchName: 'Prompt 96 Waiter Price Branch',
    );
    $waiter = User::factory()->create(['name' => 'Prompt 96 Waiter']);
    $role = prompt96AttachUserToOrganization($waiter, $organization, SystemRole::Waiter);
    prompt96GrantRolePermissions($role, [SystemPermission::ManageMenu]);
    prompt96AssignUserToBranch($waiter, $organization, $branch, $role);
    $menu = Menu::factory()->for($branch)->create([
        'name' => 'Prompt 96 Menu',
        'status' => MenuStatus::Active,
    ]);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Prompt 96 Category']);
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Prompt 96 Dish',
            'price' => '9.00',
            'is_available' => true,
        ]);

    Livewire::actingAs($waiter->fresh())
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('startEditingItem', $item->id)
        ->set('editingItemName', 'Prompt 96 Dish Renamed')
        ->set('editingItemPrice', '99.99')
        ->call('updateItem')
        ->assertHasNoErrors();

    $item->refresh();

    expect($item->name)->toBe('Prompt 96 Dish Renamed')
        ->and($item->price)->toBe('9.00')
        ->and($waiter->fresh()->hasPermission(SystemPermission::ChangePrices, $organization))->toBeFalse();
});

test('role boundaries match staff orders and payment access rules', function () {
    [$organization, $brand, $branch] = prompt96RestaurantContext(
        organizationName: 'Prompt 96 Role Boundary Group',
        brandName: 'Prompt 96 Role Boundary Brand',
        branchName: 'Prompt 96 Role Boundary Branch',
    );
    $cook = User::factory()->create(['name' => 'Prompt 96 Cook']);
    $marketer = User::factory()->create(['name' => 'Prompt 96 Marketer']);
    $accountant = User::factory()->create(['name' => 'Prompt 96 Accountant']);
    $cookRole = prompt96AttachUserToOrganization($cook, $organization, SystemRole::Cook);
    $marketerRole = prompt96AttachUserToOrganization($marketer, $organization, SystemRole::Marketer);
    $accountantRole = prompt96AttachUserToOrganization($accountant, $organization, SystemRole::Accountant);

    prompt96GrantRolePermissions($cookRole, [SystemPermission::ViewKitchen]);
    prompt96GrantRolePermissions($marketerRole, [SystemPermission::ViewReports]);
    prompt96GrantRolePermissions($accountantRole, [SystemPermission::ViewPayments]);
    prompt96AssignUserToBranch($cook, $organization, $branch, $cookRole);
    prompt96AssignUserToBranch($marketer, $organization, $branch, $marketerRole);
    prompt96AssignUserToBranch($accountant, $organization, $branch, $accountantRole);

    $this->actingAs($cook)
        ->get(route('organizations.staff.index', $organization))
        ->assertForbidden();

    expect(app(ResolveWaiterAccessibleBranchIdsAction::class)
        ->handle($marketer->fresh(), SystemPermission::ConfirmOrders))
        ->toBeEmpty();

    $paymentAccess = app(ResolvePaymentAccessibleBranchIdsAction::class);

    expect($paymentAccess->viewableBranchIds($accountant->fresh())->all())->toBe([$branch->id])
        ->and($paymentAccess->manageableBranchIds($accountant->fresh())->all())->toBe([$branch->id])
        ->and($accountant->fresh()->hasPermission(SystemPermission::ManageMenu, $organization))->toBeFalse();

    $this->actingAs($accountant)
        ->get(route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]))
        ->assertForbidden();
});

test('superadmin bypasses organization and branch level restrictions', function () {
    [$firstOrganization, , $firstBranch] = prompt96RestaurantContext(
        organizationName: 'Prompt 96 Superadmin First Group',
        brandName: 'Prompt 96 Superadmin First Brand',
        branchName: 'Prompt 96 Superadmin First Branch',
    );
    [, , $secondBranch] = prompt96RestaurantContext(
        organizationName: 'Prompt 96 Superadmin Second Group',
        brandName: 'Prompt 96 Superadmin Second Brand',
        branchName: 'Prompt 96 Superadmin Second Branch',
    );
    $superadmin = prompt96Superadmin();

    expect($superadmin->canAccessOrganization($firstOrganization))->toBeTrue()
        ->and($superadmin->canAccessBranch($firstBranch, $firstOrganization))->toBeTrue()
        ->and($superadmin->hasPermission(SystemPermission::ChangePrices, $firstOrganization))->toBeTrue()
        ->and(app(ResolveWaiterAccessibleBranchIdsAction::class)->handle($superadmin)->all())
        ->toContain($firstBranch->id, $secondBranch->id);

    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee('Prompt 96 Superadmin First Group')
        ->assertSee('Prompt 96 Superadmin Second Group');
});

function prompt96RestaurantContext(
    string $organizationName,
    string $brandName,
    string $branchName,
): array {
    $owner = User::factory()->create(['name' => $organizationName.' Owner']);
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => $branchName]);

    return [$organization, $brand, $branch, $owner->fresh()];
}

function prompt96AttachUserToOrganization(User $user, Organization $organization, SystemRole $roleCode): Role
{
    $role = Role::query()
        ->where('code', $roleCode->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $role;
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function prompt96GrantRolePermissions(Role $role, array $permissions): void
{
    $permissionRows = Permission::query()
        ->whereIn('code', array_map(
            fn (SystemPermission $permission): string => $permission->value,
            $permissions,
        ))
        ->get();

    foreach ($permissionRows as $permission) {
        $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
    }
}

function prompt96AssignUserToBranch(User $user, Organization $organization, Branch $branch, Role $role): void
{
    BranchUser::query()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'status' => OrganizationUserStatus::Active,
        'assigned_at' => now(),
        'assigned_by_user_id' => null,
    ]);
}

function prompt96Superadmin(): User
{
    $user = User::factory()->create([
        'name' => 'Prompt 96 Superadmin',
        'email' => 'prompt-96-superadmin@example.test',
    ]);
    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    return $user->fresh();
}
