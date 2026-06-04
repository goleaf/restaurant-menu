<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Index;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
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

test('branch page shows simple restaurant setup wizard', function () {
    [$organization, $brand, $owner] = createOrganizationBrand();

    Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Bella Setup Branch']);

    Livewire::actingAs($owner)
        ->test(Index::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee('Настроить ресторан')
        ->assertSee('Создать филиал')
        ->assertSee('Добавить зоны')
        ->assertSee('Добавить столы')
        ->assertSee('Сгенерировать QR')
        ->assertSee('Напечатать QR')
        ->assertSee('Открыть гостевое меню');
});

test('owner can create update and delete branch', function () {
    [$organization, $brand, $owner] = createOrganizationBrand();

    Livewire::actingAs($owner)
        ->test(Index::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee('No branches yet.')
        ->set('name', 'Bella Pizza Vilnius Old Town')
        ->set('address', 'Pilies 1')
        ->set('city', 'Vilnius')
        ->set('country', 'Lithuania')
        ->set('timezone', 'Europe/Vilnius')
        ->set('currency', 'EUR')
        ->set('isActive', true)
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('Bella Pizza Vilnius Old Town');

    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->where('name', 'Bella Pizza Vilnius Old Town')
        ->firstOrFail();

    expect($branch->organization_id)->toBe($organization->id);
    expect($branch->is_active)->toBeTrue();

    Livewire::actingAs($owner)
        ->test(Index::class, ['organization' => $organization, 'brand' => $brand])
        ->call('startEditing', $branch->id)
        ->assertSet('editingName', 'Bella Pizza Vilnius Old Town')
        ->set('editingName', 'Bella Pizza Kaunas Center')
        ->set('editingAddress', 'Laisves 10')
        ->set('editingCity', 'Kaunas')
        ->set('editingCountry', 'Lithuania')
        ->set('editingTimezone', 'Europe/Vilnius')
        ->set('editingCurrency', 'EUR')
        ->set('editingIsActive', false)
        ->call('update')
        ->assertHasNoErrors()
        ->assertSee('Bella Pizza Kaunas Center')
        ->call('confirmDelete', $branch->id)
        ->call('delete')
        ->assertDontSee('Bella Pizza Kaunas Center');

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeFalse();
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
        ->test(Index::class, ['organization' => $organization, 'brand' => $brand])
        ->set('name', 'Bella Pizza Kaunas Center')
        ->set('address', 'Laisves 10')
        ->set('city', 'Kaunas')
        ->set('country', 'Lithuania')
        ->set('timezone', 'Europe/Vilnius')
        ->set('currency', 'EUR')
        ->call('create')
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
        ->test(Index::class, ['organization' => $organization, 'brand' => $brand])
        ->set('name', 'Blocked Branch')
        ->call('create')
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
        ->test(Index::class, ['organization' => $organization, 'brand' => $brand])
        ->set('name', 'Bella Pizza Trakai')
        ->set('address', 'Karaimu 5')
        ->set('city', 'Trakai')
        ->set('country', 'Lithuania')
        ->set('timezone', 'Europe/Vilnius')
        ->set('currency', 'EUR')
        ->call('create')
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

function createOrganizationBrand(string $organizationName = 'Food Group', string $brandName = 'Bella Pizza'): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);

    return [$organization, $brand, $owner];
}
