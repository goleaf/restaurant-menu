<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Onboarding\RestaurantSetup;
use App\Livewire\Restaurants\IdentityEditor;
use App\Livewire\Restaurants\Index;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Permission;
use App\Models\RestaurantOnboarding;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('branches table belongs to brand and organization with working fields', function () {
    expect(Schema::hasTable('branches'))->toBeTrue();
    expect(Schema::hasColumns('branches', [
        'organization_id',
        'brand_id',
        'name',
        'address',
        'city',
        'country',
        'timezone',
        'currency',
        'is_active',
    ]))->toBeTrue();
});

test('branch page requires authentication', function () {
    [$organization, $brand] = createOrganizationBrand();

    $this->get(route('organizations.brands.branches.index', [$organization, $brand]))
        ->assertRedirect(route('login'));
});

test('active organization member can see branches inside brand', function () {
    [$organization, $brand] = createOrganizationBrand();
    $member = User::factory()->create();
    $otherBrand = Brand::factory()->for($organization)->create(['name' => 'Sushi Master']);
    $directorRole = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $member->id => [
            'role_id' => $directorRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);

    Branch::factory()->for($organization)->for($brand)->create(['name' => 'Bella Pizza Vilnius Old Town']);
    Branch::factory()->for($organization)->for($otherBrand)->create(['name' => 'Sushi Master Kaunas Center']);

    Livewire::actingAs($member)
        ->test(Index::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee('Bella Pizza')
        ->assertSee('Bella Pizza Vilnius Old Town')
        ->assertDontSee('Sushi Master Kaunas Center');
});

test('restaurant creation and preparation use the canonical addressable wizard', function (): void {
    app()->setLocale('ru');
    [$organization, $brand, $owner] = createOrganizationBrand();
    $branch = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Bella Setup Branch']);
    Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand'))
        ->assertSee(__('center.add'))->assertSee(__('center.properties'))->assertDontSeeHtml('wire:submit="create"');
    Livewire::actingAs($owner)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->call('continueSetup')->assertRedirect();
    $setup = RestaurantOnboarding::query()->where('branch_id', $branch->id)->sole();
    Livewire::actingAs($owner)->test(RestaurantSetup::class, ['setup' => $setup->id])
        ->assertSee(__('center.details'))->assertSee(__('center.rooms'))->assertSee(__('center.menu'))->assertSee(__('center.review'));
});

test('owner can create update and delete branch', function () {
    [$organization, $brand, $owner] = createOrganizationBrand();

    Livewire::actingAs($owner)
        ->test(RestaurantSetup::class)->set('form.organizationId', $organization->id)->set('form.brandId', $brand->id)
        ->set('form.branchName', 'Bella Pizza Vilnius Old Town')
        ->set('form.branchAddress', 'Pilies 1')
        ->set('form.branchCity', 'Vilnius')
        ->set('form.branchCountryCode', 'LT')
        ->set('form.branchTimezone', 'Europe/Vilnius')
        ->set('form.branchCurrency', 'EUR')
        ->call('createRestaurant')
        ->assertHasNoErrors()
        ->assertSee('Bella Pizza Vilnius Old Town');

    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->where('name', 'Bella Pizza Vilnius Old Town')
        ->firstOrFail();

    expect($branch->organization_id)->toBe($organization->id);
    expect($branch->is_active)->toBeFalse();
    Livewire::actingAs($owner)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->set('form.isActive', true)->call('save')->assertHasNoErrors();
    expect($branch->fresh()->is_active)->toBeTrue();

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->assertSet('form.name', 'Bella Pizza Vilnius Old Town')
        ->set('form.name', 'Bella Pizza Kaunas Center')
        ->set('form.address', 'Laisves 10')
        ->set('form.city', 'Kaunas')
        ->set('form.country', 'Lithuania')
        ->set('form.timezone', 'Europe/Vilnius')
        ->set('form.currency', 'EUR')
        ->set('form.isActive', false)
        ->set('form.suspensionReason', 'Temporarily closing this branch.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Bella Pizza Kaunas Center')
        ->set('confirmation', 'Bella Pizza Kaunas Center')->call('changeLifecycle')->assertHasNoErrors();

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeFalse();
});

test('owner cannot archive branch that contains an active order', function () {
    [$organization, $brand, $owner] = createOrganizationBrand();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $servicePoint = ServicePoint::factory()->for($branch)->blocked()->create();
    $closedSession = TableSession::factory()->forServicePoint($servicePoint)->closed()->create();
    Order::factory()->forTableSession($closedSession)->confirmedByWaiter()->create();

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->set('confirmation', $branch->name)->call('changeLifecycle')
        ->assertHasErrors('structureDeletion');

    expect($branch->fresh())->not->toBeNull();
});

test('director can manage branches in their organization', function () {
    [$organization, $brand] = createOrganizationBrand();
    $director = User::factory()->create();
    $directorRole = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $director->id => [
            'role_id' => $directorRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);

    Livewire::actingAs($director)
        ->test(RestaurantSetup::class)->set('form.organizationId', $organization->id)->set('form.brandId', $brand->id)
        ->set('form.branchName', 'Bella Pizza Kaunas Center')
        ->set('form.branchAddress', 'Laisves 10')
        ->set('form.branchCity', 'Kaunas')
        ->set('form.branchCountryCode', 'LT')
        ->set('form.branchTimezone', 'Europe/Vilnius')
        ->set('form.branchCurrency', 'EUR')
        ->call('createRestaurant')
        ->assertHasNoErrors()
        ->assertSee('Bella Pizza Kaunas Center');
});

test('member without manager role cannot mutate branches', function () {
    [$organization, $brand] = createOrganizationBrand();
    $waiter = User::factory()->create();
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $waiter->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);

    Livewire::actingAs($waiter)
        ->test(RestaurantSetup::class)->set('form.organizationId', $organization->id)->set('form.brandId', $brand->id)
        ->set('form.branchName', 'Blocked Branch')->set('form.branchAddress', 'Main 1')->set('form.branchCity', 'Vilnius')
        ->set('form.branchCountryCode', 'LT')->set('form.branchTimezone', 'UTC')->set('form.branchCurrency', 'EUR')
        ->call('createRestaurant')
        ->assertForbidden();
});

test('organization scoped manage branches permission can manage branches', function () {
    [$organization, $brand] = createOrganizationBrand();
    $waiter = User::factory()->create();
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $manageBranches = Permission::query()
        ->where('code', SystemPermission::ManageBranches->value)
        ->firstOrFail();

    $waiterRole->permissions()->updateExistingPivot($manageBranches->id, ['enabled' => true]);

    $organization->users()->syncWithoutDetachingOrFail([
        $waiter->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);

    Livewire::actingAs($waiter)
        ->test(RestaurantSetup::class)->set('form.organizationId', $organization->id)->set('form.brandId', $brand->id)
        ->set('form.branchName', 'Bella Pizza Trakai')
        ->set('form.branchAddress', 'Karaimu 5')
        ->set('form.branchCity', 'Trakai')
        ->set('form.branchCountryCode', 'LT')
        ->set('form.branchTimezone', 'Europe/Vilnius')
        ->set('form.branchCurrency', 'EUR')
        ->call('createRestaurant')
        ->assertHasNoErrors()
        ->assertSee('Bella Pizza Trakai');
});

test('brand must belong to route organization', function () {
    [$organization] = createOrganizationBrand();
    [$otherOrganization, $otherBrand, $otherOwner] = createOrganizationBrand('Other Group', 'Other Brand');

    Livewire::actingAs($otherOwner)
        ->test(Index::class, ['organization' => $organization, 'brand' => $otherBrand])
        ->assertForbidden();
});

test('organization branch manager is authorized to restore an archived branch', function () {
    [$organization, $brand, $owner] = createOrganizationBrand();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $branch->deleteOrFail();

    expect(Gate::forUser($owner)->allows('restore', $branch))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $branch))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $branch))->toBeFalse();
});

test('branch manager can view and restore an archived branch without a page reload', function () {
    [$organization, $brand, $owner] = createOrganizationBrand();
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Archived Branch']);
    $branch->deleteOrFail();

    Livewire::actingAs($owner)
        ->test(Index::class, ['organization' => $organization, 'brand' => $brand])
        ->assertDontSee('Archived Branch')
        ->set('filters.lifecycle', 'archived')->assertSee('Archived Branch');
    Livewire::actingAs($owner)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->set('confirmation', $branch->name)->call('changeLifecycle')->assertHasNoErrors();

    expect($branch->fresh())->not->toBeNull();
});

test('locked canonical editor cannot retarget restoration to another brand', function () {
    [$organization, $brand, $owner] = createOrganizationBrand();
    $foreignBrand = Brand::factory()->for($organization)->create(['name' => 'Foreign Brand']);
    $foreignBranch = Branch::factory()->for($organization)->for($foreignBrand)->create();
    $foreignBranch->deleteOrFail();

    $editableBranch = Branch::factory()->for($organization)->for($brand)->create();
    $editor = Livewire::actingAs($owner)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $editableBranch->id]);
    expect(fn () => $editor->set('objectId', $foreignBranch->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(Branch::withTrashed()->findOrFail($foreignBranch->id)->trashed())->toBeTrue();
});

function createOrganizationBrand(string $organizationName = 'Food Group', string $brandName = 'Bella Pizza'): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);

    return [$organization, $brand, $owner];
}
