<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Index;
use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemRolesSeeder::class);
});

test('brands table belongs to organizations', function () {
    expect(Schema::hasTable('brands'))->toBeTrue();
    expect(Schema::hasColumns('brands', [
        'organization_id',
        'name',
    ]))->toBeTrue();
});

test('brand page requires authentication', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Food Group']);

    $this->get(route('organizations.brands.index', $organization))
        ->assertRedirect(route('login'));
});

test('active organization member can see brands inside organization', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Food Group']);
    $otherOrganization = (new CreateOrganizationAction)->handle(User::factory()->create(), ['name' => 'Other Group']);
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

    Brand::factory()->for($organization)->create(['name' => 'Bella Pizza']);
    Brand::factory()->for($otherOrganization)->create(['name' => 'Sushi Master']);

    Livewire::actingAs($member)
        ->test(Index::class, ['organization' => $organization])
        ->assertSee('Food Group')
        ->assertSee('Bella Pizza')
        ->assertDontSee('Sushi Master');
});

test('owner can create update and delete brand', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Food Group']);

    Livewire::actingAs($owner)
        ->test(Index::class, ['organization' => $organization])
        ->assertSee('No brands yet.')
        ->set('name', 'Bella Pizza')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('Bella Pizza');

    $brand = Brand::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Bella Pizza')
        ->firstOrFail();

    Livewire::actingAs($owner)
        ->test(Index::class, ['organization' => $organization])
        ->call('startEditing', $brand->id)
        ->assertSet('editingName', 'Bella Pizza')
        ->set('editingName', 'Bella Pasta')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSee('Bella Pasta')
        ->call('confirmDelete', $brand->id)
        ->call('delete')
        ->assertDontSee('Bella Pasta');

    expect(Brand::query()->whereKey($brand->id)->exists())->toBeFalse();
});

test('director can manage brands in their organization', function () {
    $owner = User::factory()->create();
    $director = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Food Group']);
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
        ->test(Index::class, ['organization' => $organization])
        ->set('name', 'Sushi Master')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('Sushi Master');
});

test('member without manager role cannot mutate brands', function () {
    $owner = User::factory()->create();
    $waiter = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Food Group']);
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
        ->test(Index::class, ['organization' => $organization])
        ->set('name', 'Blocked Brand')
        ->call('create')
        ->assertForbidden();
});

test('non member cannot access organization brands', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Food Group']);

    Livewire::actingAs($stranger)
        ->test(Index::class, ['organization' => $organization])
        ->assertForbidden();
});
