<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Restaurants\Index;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\RestaurantCenterQuery;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Private archive company']);
    $this->brand = Brand::factory()->for($this->organization)->create(['name' => 'Private archive brand']);
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create(['name' => 'Private archive restaurant']);
    $this->waiter = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->waiter)->forSystemRole(SystemRole::Waiter)->create();
    BranchUser::factory()->forBranch($this->branch)->forUser($this->waiter)->create();
});

it('keeps archived organization names out of lists without the restoration capability', function (): void {
    $this->organization->delete();
    expect(Gate::forUser($this->waiter)->allows('restore', $this->organization))->toBeFalse();
    $query = app(RestaurantCenterQuery::class);
    expect($query->organizations($this->waiter, '', 'archived')->items())->toBeEmpty()
        ->and($query->organizations($this->owner, '', 'archived')->pluck('id')->all())->toBe([$this->organization->id]);
});

it('keeps archived brand names out of lists and selected options without restoration rights', function (): void {
    $this->brand->delete();
    expect(Gate::forUser($this->waiter)->allows('restore', $this->brand))->toBeFalse();
    $query = app(RestaurantCenterQuery::class);
    expect($query->brands($this->waiter, $this->organization->id, '', 'archived')->items())->toBeEmpty()
        ->and($query->filterBrands($this->waiter, $this->organization->id, '', $this->brand->id))->toBeEmpty()
        ->and($query->brands($this->owner, $this->organization->id, '', 'archived')->pluck('id')->all())->toBe([$this->brand->id]);
});

it('keeps archived restaurant names scoped by restoration rights and active assignments', function (): void {
    $this->branch->delete();
    $query = app(RestaurantCenterQuery::class);
    expect(Gate::forUser($this->waiter)->allows('restore', $this->branch))->toBeFalse()
        ->and($query->restaurants($this->waiter, ['lifecycle' => 'archived'])->items())->toBeEmpty()
        ->and($query->restaurants($this->owner, ['lifecycle' => 'archived'])->pluck('id')->all())->toBe([$this->branch->id]);
    $permission = Permission::query()->where('code', SystemPermission::ManageBranches->value)->sole();
    PermissionUserOverride::factory()->forUser($this->owner)->forOrganization($this->organization)->forPermission($permission)->denied()->create();
    expect(Gate::forUser($this->owner)->allows('restore', $this->branch))->toBeFalse()
        ->and($query->restaurants($this->owner, ['lifecycle' => 'archived'])->items())->toBeEmpty();
});

it('keeps ordinary operational resolution active only and applies exact grants to archived assignments', function (): void {
    $this->branch->delete();
    $permission = Permission::query()->where('code', SystemPermission::ManageBranches->value)->sole();
    PermissionUserOverride::factory()->forUser($this->waiter)->forOrganization($this->organization)->forPermission($permission)->allowed()->create();
    $access = app(ResolveWaiterAccessibleBranchIdsAction::class);
    expect($access->handle($this->owner, SystemPermission::ManageBranches)->all())->toBeEmpty()
        ->and($access->handle($this->waiter, SystemPermission::ManageBranches, includeArchived: true)->all())->toBe([$this->branch->id])
        ->and(Gate::forUser($this->waiter)->allows('restore', $this->branch))->toBeTrue();
    BranchUser::query()->where('user_id', $this->waiter->id)->where('branch_id', $this->branch->id)->update(['status' => 'suspended']);
    expect($access->handle($this->waiter, SystemPermission::ManageBranches, includeArchived: true)->all())->toBeEmpty()
        ->and(app(RestaurantCenterQuery::class)->restaurants($this->waiter, ['lifecycle' => 'archived'])->items())->toBeEmpty();
});

it('clears an incompatible parent selection from an initial bookmarked URL before loading rows', function (): void {
    $otherOrganization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Another company']);
    $otherBrand = Brand::factory()->for($otherOrganization)->create();

    Livewire::actingAs($this->owner)->withQueryParams(['organization' => $this->organization->id, 'brand' => $otherBrand->id])
        ->test(Index::class)->assertSet('filters.brandId', '')
        ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('id')->all() === [$this->branch->id]);
});

it('retains list context when opening a child structure and clears only incompatible filters', function (): void {
    $query = app(RestaurantCenterQuery::class);
    $parameters = ['view' => 'structure', 'q' => 'Private', 'sort' => 'name_desc', 'lifecycle' => 'archived', 'organizationsPage' => 2];
    $row = $query->row($this->organization, 'organization', $parameters);
    parse_str(parse_url($row['children'], PHP_URL_QUERY), $child);
    expect($child)->toMatchArray(['view' => 'structure', 'organization' => (string) $this->organization->id,
        'q' => 'Private', 'sort' => 'name_desc', 'lifecycle' => 'archived', 'organizationsPage' => '2']);
});

it('prepares row logos from the selected identity without any extra queries', function (): void {
    $query = app(RestaurantCenterQuery::class);
    $this->branch->update(['logo_path' => 'branches/logos/fixture.png']);
    $resource = $query->restaurants($this->owner, [])->first();
    $queries = countDatabaseQueries(function () use ($query, $resource): void {
        $row = $query->row($resource, 'branch', []);
        expect($row['logo_url'])->toBe($resource->logoUrl());
    });
    expect($queries)->toBe(0);
});

it('retains archived parent names only on an authorized archive card', function (): void {
    $this->branch->delete();
    $this->brand->delete();
    $query = app(RestaurantCenterQuery::class);
    expect(Gate::forUser($this->owner)->allows('restore', $this->branch))->toBeTrue();
    $identity = $query->identity($this->owner, 'branch', $this->branch->id);
    expect($identity->organization->name)->toBe($this->organization->name)
        ->and($identity->brand->name)->toBe($this->brand->name);
    $this->organization->delete();
    expect(fn () => $query->identity($this->owner, 'branch', $this->branch->id))->toThrow(AuthorizationException::class);
});

it('does not confirm a foreign organization brand relationship while normalizing filters', function (): void {
    $foreignBrand = Brand::factory()->create();
    $filters = ['organization' => (string) $foreignBrand->organization_id, 'brand' => (string) $foreignBrand->id, 'lifecycle' => 'active'];
    expect(app(RestaurantCenterQuery::class)->consistentFilters($this->owner, $filters)['brand'])->toBe('');
});

it('keeps the exact superadmin archive policy and loads archived ancestry when the branch is authorized', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->sole());
    $this->branch->delete();
    $this->brand->delete();
    $this->organization->delete();
    $query = app(RestaurantCenterQuery::class);
    expect(Gate::forUser($superadmin)->allows('restore', $this->organization))->toBeFalse()
        ->and($query->organizations($superadmin, '', 'archived')->items())->toBeEmpty()
        ->and(Gate::forUser($superadmin)->allows('restore', $this->branch))->toBeTrue();
    $identity = $query->identity($superadmin, 'branch', $this->branch->id);
    expect($identity->organization->name)->toBe($this->organization->name)
        ->and($identity->brand->name)->toBe($this->brand->name);
});
