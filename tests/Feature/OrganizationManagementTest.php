<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Index;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemRolesSeeder::class);
});

test('organizations page requires authentication', function () {
    $this->get(route('organizations.index'))
        ->assertRedirect(route('login'));
});

test('user can create organization and becomes owner', function () {
    $user = User::factory()->create();
    $ownerRole = Role::query()
        ->where('code', SystemRole::Owner->value)
        ->firstOrFail();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('No organizations yet.')
        ->set('name', 'North Star Hospitality')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('North Star Hospitality');

    $organization = Organization::query()
        ->where('name', 'North Star Hospitality')
        ->firstOrFail();

    expect($organization->owner_user_id)->toBe($user->id);
    expect($user->fresh()->roles()->where('roles.code', SystemRole::Owner->value)->exists())->toBeTrue();

    $linkedOrganization = $user->fresh()
        ->organizations()
        ->whereKey($organization->id)
        ->firstOrFail();

    expect($linkedOrganization->pivot->role_id)->toBe($ownerRole->id);
});

test('user can be linked to multiple organizations', function () {
    $user = User::factory()->create();
    $createOrganization = new CreateOrganizationAction;

    $createOrganization->handle($user, ['name' => 'Alpha Group']);
    $createOrganization->handle($user, ['name' => 'Beta Group']);

    $organizationNames = $user->organizations()
        ->select(['organizations.name'])
        ->orderBy('organizations.name')
        ->pluck('organizations.name')
        ->all();

    expect($organizationNames)->toBe([
        'Alpha Group',
        'Beta Group',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Alpha Group')
        ->assertSee('Beta Group');
});

test('user sees only linked organizations', function () {
    $user = User::factory()->create();
    $otherOwner = User::factory()->create();
    $createOrganization = new CreateOrganizationAction;
    $directorRole = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();

    $ownedOrganization = $createOrganization->handle($user, ['name' => 'Visible Owned']);
    $memberOrganization = $createOrganization->handle($otherOwner, ['name' => 'Visible Member']);
    $hiddenOrganization = $createOrganization->handle($otherOwner, ['name' => 'Hidden Company']);

    $memberOrganization->users()->syncWithoutDetachingOrFail([
        $user->id => ['role_id' => $directorRole->id],
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee($ownedOrganization->name)
        ->assertSee($memberOrganization->name)
        ->assertDontSee($hiddenOrganization->name);
});

test('owner can update and delete organization', function () {
    $user = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($user, ['name' => 'Old Company']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startEditing', $organization->id)
        ->assertSet('editingName', 'Old Company')
        ->set('editingName', 'New Company')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSee('New Company')
        ->call('confirmDelete', $organization->id)
        ->call('delete')
        ->assertDontSee('New Company');

    expect(Organization::query()->whereKey($organization->id)->exists())->toBeFalse();
    expect($user->fresh()->organizations()->whereKey($organization->id)->exists())->toBeFalse();
});

test('owner cannot archive organization that contains an active order', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Active Order Company']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $servicePoint = ServicePoint::factory()->for($branch)->blocked()->create();
    $closedSession = TableSession::factory()->forServicePoint($servicePoint)->closed()->create();
    Order::factory()->forTableSession($closedSession)->served()->create();

    Livewire::actingAs($owner)
        ->test(Index::class)
        ->call('confirmDelete', $organization->id)
        ->call('delete')
        ->assertHasErrors('structureDeletion');

    expect($organization->fresh())->not->toBeNull();
});

test('linked non owner cannot manage organization', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Owner Managed']);
    $directorRole = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $member->id => ['role_id' => $directorRole->id],
    ]);

    Livewire::actingAs($member)
        ->test(Index::class)
        ->assertSee($organization->name)
        ->call('startEditing', $organization->id)
        ->assertForbidden();
});

test('owner is authorized to restore an archived organization', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Restorable Company']);
    $organization->deleteOrFail();

    expect(Gate::forUser($owner)->allows('restore', $organization))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $organization))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $organization))->toBeFalse();
});

test('owner can view and restore an archived organization without a page reload', function () {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Archived Company']);
    $organization->deleteOrFail();

    Livewire::actingAs($owner)
        ->test(Index::class)
        ->assertDontSee('Archived Company')
        ->set('lifecycle', 'archived')
        ->assertSee('Archived Company')
        ->call('restore', $organization->id)
        ->assertHasNoErrors();

    expect($organization->fresh())->not->toBeNull();
});

test('livewire payload cannot restore an organization outside the current tenant memberships', function () {
    $owner = User::factory()->create();
    $foreignOwner = User::factory()->create();
    (new CreateOrganizationAction)->handle($owner, ['name' => 'Owned Company']);
    $foreignOrganization = (new CreateOrganizationAction)->handle($foreignOwner, ['name' => 'Foreign Archived Company']);
    $foreignOrganization->deleteOrFail();

    $caughtException = null;

    try {
        Livewire::actingAs($owner)
            ->test(Index::class)
            ->call('restore', $foreignOrganization->id);
    } catch (Throwable $exception) {
        $caughtException = $exception;
    }

    expect($caughtException)->toBeInstanceOf(ModelNotFoundException::class)
        ->and(Organization::withTrashed()->findOrFail($foreignOrganization->id)->trashed())->toBeTrue();
});
