<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Index;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
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

test('owner cannot archive brand that contains an active order', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Active Brand Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Active Brand']);
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $servicePoint = ServicePoint::factory()->for($branch)->blocked()->create();
    $closedSession = TableSession::factory()->forServicePoint($servicePoint)->closed()->create();
    Order::factory()->forTableSession($closedSession)->ready()->create();

    Livewire::actingAs($owner)
        ->test(Index::class, ['organization' => $organization])
        ->call('confirmDelete', $brand->id)
        ->call('delete')
        ->assertHasErrors('structureDeletion');

    expect($brand->fresh())->not->toBeNull();
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

test('organization brand manager is authorized to restore an archived brand', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Restorable Brand Group']);
    $brand = Brand::factory()->for($organization)->create();
    $brand->deleteOrFail();

    expect(Gate::forUser($owner)->allows('restore', $brand))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $brand))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $brand))->toBeFalse();
});

test('brand manager can view and restore an archived brand without a page reload', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Parent Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Archived Brand']);
    $brand->deleteOrFail();

    Livewire::actingAs($owner)
        ->test(Index::class, ['organization' => $organization])
        ->assertDontSee('Archived Brand')
        ->set('lifecycle', 'archived')
        ->assertSee('Archived Brand')
        ->call('restore', $brand->id)
        ->assertHasNoErrors();

    expect($brand->fresh())->not->toBeNull();
});

test('livewire payload cannot restore a brand from another organization', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Owned Group']);
    $foreignOrganization = (new CreateOrganizationAction)->handle(User::factory()->create(), ['name' => 'Foreign Group']);
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create();
    $foreignBrand->deleteOrFail();

    $caughtException = null;

    try {
        Livewire::actingAs($owner)
            ->test(Index::class, compact('organization'))
            ->call('restore', $foreignBrand->id);
    } catch (Throwable $exception) {
        $caughtException = $exception;
    }

    expect($caughtException)->toBeInstanceOf(ModelNotFoundException::class)
        ->and(Brand::withTrashed()->findOrFail($foreignBrand->id)->trashed())->toBeTrue();
});
