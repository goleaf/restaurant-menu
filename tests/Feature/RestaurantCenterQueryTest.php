<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\SystemPermission;
use App\Livewire\Restaurants\IdentityEditor;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Services\Organizations\RestaurantCenterQuery;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

it('clears the parent name search only when opening its child level', function (): void {
    $organization = Organization::factory()->create(['name' => 'North Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Daily Kitchen']);
    foreach ([[$organization, 'organization'], [$brand, 'brand']] as [$resource, $kind]) {
        $parameters = ['view' => 'structure', 'q' => $resource->name, 'sort' => 'name_desc',
            'lifecycle' => 'active', 'organizationsPage' => 2, 'brandsPage' => 5, 'page' => 3];
        $row = app(RestaurantCenterQuery::class)->row($resource, $kind, $parameters);
        parse_str(parse_url($row['children'], PHP_URL_QUERY), $children);
        parse_str(parse_url($row['properties'], PHP_URL_QUERY), $properties);
        expect($children['q'])->toBe('')->and($children['sort'])->toBe('name_desc')
            ->and($children['lifecycle'])->toBe('active')
            ->and($children[$kind === 'organization' ? 'brandsPage' : 'page'])->toBe('1')
            ->and($properties['q'])->toBe($resource->name)->and($properties['organizationsPage'])->toBe('2');
    }
});

it('returns authorized restaurants in a bounded page with actor-owned continuation only', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Business']);
    $brand = Brand::factory()->for($organization)->create();
    $points = Branch::factory()->count(25)->sequence(fn ($sequence) => ['name' => 'Restaurant '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->for($organization)->for($brand)->create();
    $other = Branch::factory()->create(['name' => 'Private other tenant']);
    $attempt = RestaurantOnboarding::factory()->create(['user_id' => $actor->id, 'organization_id' => $organization->id,
        'brand_id' => $brand->id, 'branch_id' => $points->first()->id]);
    $result = app(RestaurantCenterQuery::class)->restaurants($actor, []);
    expect($result->items())->toHaveCount(20)->and($result->hasMorePages())->toBeTrue();
    $all = collect($result->items());
    expect($all->pluck('id'))->not->toContain($other->id);
    $filtered = app(RestaurantCenterQuery::class)->restaurants($actor, ['organization' => (string) $organization->id, 'setup' => 'unfinished']);
    expect($filtered->items())->toHaveCount(20)->and($filtered->items()[0]->id)->toBe($attempt->branch_id);
});

it('keeps empty organizations and brands discoverable without exposing foreign structure', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Empty allowed organization']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Empty allowed brand']);
    $foreign = Brand::factory()->create();
    $query = app(RestaurantCenterQuery::class);
    expect($query->organizations($actor, '')->pluck('id')->all())->toBe([$organization->id]);
    expect($query->brands($actor, $organization->id, '')->pluck('id')->all())->toBe([$brand->id]);
    expect(fn () => $query->brands($actor, $foreign->organization_id, ''))->toThrow(AuthorizationException::class);
});

it('filters unconfirmed preparation by restaurant history without exposing another actors attempt', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Preparation business']);
    $brand = Brand::factory()->for($organization)->create();
    $unconfirmed = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Unconfirmed']);
    $confirmed = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Confirmed but inactive', 'is_active' => false]);
    $other = User::factory()->create();
    RestaurantOnboarding::factory()->for($other)->for($organization)->for($brand)->for($confirmed)->create(['completed_at' => now()]);
    $query = app(RestaurantCenterQuery::class);
    $rows = $query->restaurants($actor, ['setup' => 'unfinished']);
    expect($rows->pluck('id')->all())->toBe([$unconfirmed->id]);
    $projection = $query->row($rows->first(), 'branch', []);
    expect($projection['setup'])->toBeNull()->and($projection['missing_step'])->toBe('confirmation');
    $all = $query->restaurants($actor, []);
    $confirmedRow = $query->row($all->firstWhere('id', $confirmed->id), 'branch', []);
    expect($confirmedRow['preparation_confirmed'])->toBeTrue()->and($confirmedRow['setup'])->toBeNull();
});

it('offers continuation only while the owned attempt remains authorized with a bounded query count', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Continuation permissions']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $setup = RestaurantOnboarding::factory()->for($actor)->for($organization)->for($brand)->for($branch)->create(['purpose' => 'additional']);
    $query = app(RestaurantCenterQuery::class);
    $fewQueries = countDatabaseQueries(fn () => $query->restaurants($actor, []));
    expect($query->row($query->restaurants($actor, [])->first(), 'branch', [])['setup'])->toBe(route('restaurants.setup', ['setup' => $setup->id]));
    Branch::factory()->count(19)->for($organization)->for($brand)->create()->each(function (Branch $item) use ($actor, $organization, $brand): void {
        RestaurantOnboarding::factory()->for($actor)->for($organization)->for($brand)->for($item)->create(['purpose' => 'additional']);
    });
    $manyQueries = countDatabaseQueries(fn () => $query->restaurants($actor, []));
    expect($manyQueries)->toBe($fewQueries);
    $permission = Permission::query()->where('code', SystemPermission::ManageBranches->value)->sole();
    PermissionUserOverride::factory()->forUser($actor)->forOrganization($organization)->forPermission($permission)->denied()->create();
    expect(Gate::forUser($actor)->allows('view', $branch))->toBeTrue();
    $rows = $query->restaurants($actor, []);
    expect($rows->items())->toHaveCount(20);
    foreach ($rows as $resource) {
        expect($query->row($resource, 'branch', [])['setup'])->toBeNull();
    }
    Livewire\Livewire::actingAs($actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->assertViewHas('canContinueSetup', false);
});

it('does not offer the editor continuation for another actors private attempt', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Private preparation']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    RestaurantOnboarding::factory()->for(User::factory()->create())->for($organization)->for($brand)->for($branch)->create(['purpose' => 'additional']);
    Livewire\Livewire::actingAs($actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->assertViewHas('canEdit', true)->assertViewHas('canContinueSetup', false)->assertDontSeeHtml('wire:click="continueSetup"');
});
