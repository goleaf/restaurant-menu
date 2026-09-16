<?php

use App\Enums\KitchenDepartmentType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Exports\Index;
use App\Livewire\Kitchen\Dashboard;
use App\Livewire\Workspace\RestaurantSwitcher;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Navigation\WorkspaceAccessQuery;
use App\Services\Navigation\WorkspaceContextResolver;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->actor)->forSystemRole(SystemRole::Owner)->active()->create();
});

function workspaceRequest(string $route, array $parameters = [], array $query = []): Request
{
    $request = Request::create(route($route, [...$parameters, ...$query]));
    $matched = app('router')->getRoutes()->match($request);
    foreach ($parameters as $key => $value) {
        $matched->setParameter($key, $value);
    }
    $request->setRouteResolver(fn () => $matched);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

test('a route bound restaurant wins over the last preference and rejects conflicting input', function () {
    $other = Branch::factory()->for($this->organization)->for($this->brand)->create();
    session()->put('workspace.preference', ['actor' => $this->actor->id, 'branch' => $other->id, 'destination' => 'menu']);
    $parameters = ['organization' => $this->organization, 'brand' => $this->brand, 'branch' => $this->branch];
    $request = workspaceRequest('organizations.brands.branches.menu.index', $parameters);
    $context = app(WorkspaceContextResolver::class)->resolve($this->actor, $request);
    expect($context->branchId)->toBe($this->branch->id)->and($context->mode)->toBe('restaurant')->and($context->destination)->toBe('menu');
    $request->query->set('branch', (string) $other->id);
    expect(fn () => app(WorkspaceContextResolver::class)->resolve($this->actor, $request))->toThrow(HttpException::class);
});

test('forbidden and malformed explicit restaurants never fall back to an accessible restaurant', function (mixed $input) {
    $request = workspaceRequest('restaurant.dashboard', [], ['branch' => $input === 'foreign' ? Branch::factory()->create()->id : $input]);
    if (is_bool($input)) {
        $request->query->set('branch', $input);
    }
    expect(fn () => app(WorkspaceContextResolver::class)->resolve($this->actor, $request))->toThrow(HttpException::class);
})->with(['foreign', 'invalid', ['array'], true, 0]);

test('narrow team settings and availability permissions remain selectable without dashboard access', function (SystemPermission $permission, string $destination) {
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $role->permissions()->detach();
    $role->permissions()->attach(Permission::query()->where('code', $permission->value)->firstOrFail(), ['enabled' => true]);
    $member = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($member)->forRole($role)->active()->create();
    $access = app(WorkspaceAccessQuery::class)->destinations($member);
    expect($access[$destination])->toContain($this->branch->id);
    $context = app(WorkspaceContextResolver::class)->resolve($member, workspaceRequest('dashboard'));
    expect($context->branchId)->toBe($this->branch->id);
})->with([
    [SystemPermission::ManageStaff, 'team'],
    [SystemPermission::ManageSettings, 'settings'],
    [SystemPermission::ChangeAvailability, 'availability'],
]);

test('two explicit tabs remain independent when their common preference changes', function () {
    $other = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $first = workspaceRequest('restaurant.dashboard', [], ['branch' => $this->branch->id]);
    $second = workspaceRequest('restaurant.dashboard', [], ['branch' => $other->id]);
    session()->put('workspace.preference', ['actor' => $this->actor->id, 'branch' => $this->branch->id, 'destination' => 'overview']);
    $resolver = app(WorkspaceContextResolver::class);
    expect($resolver->resolve($this->actor, $second)->branchId)->toBe($other->id);
    session()->put('workspace.preference', ['actor' => $this->actor->id, 'branch' => $other->id, 'destination' => 'overview']);
    expect($resolver->resolve($this->actor, $first)->branchId)->toBe($this->branch->id);
});

test('restaurant search is private bounded unicode aware and retains an explicit continuation', function () {
    $this->branch->update(['name' => 'ŽĄSIS — КАФЕ']);
    Branch::factory()->create(['name' => 'Secret café']);
    $query = app(WorkspaceAccessQuery::class);
    expect($query->search($this->actor, 'žąsis')['options'])->toHaveCount(1)
        ->and($query->search($this->actor, 'кафе')['options'])->toHaveCount(1)
        ->and($query->search($this->actor, 'Secret')['options'])->toBe([]);
    Branch::factory()->count(25)->for($this->organization)->for($this->brand)->sequence(fn ($sequence) => ['name' => 'Search restaurant '.$sequence->index])->create();
    $page = $query->search($this->actor);
    expect($page['options'])->toHaveCount(20)->and($page['next'])->not->toBeNull();
    expect($query->search($this->actor, '', $page['next'])['options'])->toHaveCount(6);
});

test('the compatible home route opens the permitted task without an intermediate dashboard', function () {
    $this->actingAs($this->actor)->get(route('dashboard'))
        ->assertRedirect(route('restaurant.dashboard', ['branch' => $this->branch->id]));
});

test('switching preserves the task and strips the previous restaurant object identifiers', function () {
    $other = Branch::factory()->for($this->organization)->for($this->brand)->create();
    Livewire::actingAs($this->actor)->test(RestaurantSwitcher::class, [
        'branchId' => $this->branch->id, 'destination' => 'menu', 'mode' => 'restaurant',
    ])->set('form.branchId', (string) $other->id)->call('choose')
        ->assertRedirect(route('organizations.brands.branches.menu.index', [$this->organization, $this->brand, $other]));
    expect(session('workspace.preference'))->toBeNull();
});

test('switcher refuses foreign branches and account changes before navigation or preference writes', function () {
    $component = Livewire::actingAs($this->actor)->test(RestaurantSwitcher::class, [
        'branchId' => $this->branch->id, 'destination' => 'menu', 'mode' => 'restaurant',
    ]);
    $component->set('form.branchId', Branch::factory()->create()->id)->call('choose')->assertForbidden();
    $component = Livewire::actingAs($this->actor)->test(RestaurantSwitcher::class, [
        'branchId' => $this->branch->id, 'destination' => 'menu', 'mode' => 'restaurant',
    ]);
    $this->actingAs(User::factory()->create());
    $component->call('remember')->assertStatus(409);
});

test('exports show only the explicit restaurant even when the actor can export both', function () {
    $other = Branch::factory()->for($this->organization)->for($this->brand)->create();
    Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id])
        ->test(Index::class)
        ->assertViewHas('exports', fn (array $exports) => array_column($exports['branches'], 'id') === [$this->branch->id]);
});

test('preparation departments cannot change the restaurant through the department filter', function () {
    $other = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $own = KitchenDepartment::factory()->for($this->branch)->create(['type' => KitchenDepartmentType::Kitchen]);
    $foreign = KitchenDepartment::factory()->for($other)->create(['type' => KitchenDepartmentType::Kitchen]);
    Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id])
        ->test(Dashboard::class)
        ->assertSet('departments', fn (array $departments) => array_column($departments, 'id') === [$own->id])
        ->set('selectedDepartmentId', (string) $foreign->id)->assertForbidden();
});

test('an already open ordering form saves only its own restaurant after another tab remembers a different one', function () {
    $other = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $tab = Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id])
        ->test(App\Livewire\Restaurant\Dashboard::class);
    session()->put('workspace.preference', ['actor' => $this->actor->id, 'branch' => $other->id, 'destination' => 'overview']);
    $tab->set('closure.temporarilyClosed', true)->set('closure.temporaryClosedReason', 'Closed for cleaning')->call('saveOrdering')->assertHasNoErrors();
    expect($this->branch->fresh()->is_temporarily_closed)->toBeTrue()->and($other->fresh()->is_temporarily_closed)->toBeFalse();
});

test('the entry explains a fallback when the remembered section is no longer allowed', function () {
    session()->put('workspace.preference', ['actor' => $this->actor->id, 'branch' => $this->branch->id, 'destination' => 'kitchen']);
    $this->actingAs($this->actor)->get(route('dashboard'))
        ->assertRedirect(route('restaurant.dashboard', ['branch' => $this->branch->id, 'workspace_notice' => 'section_unavailable']));
});

test('aggregate overview is explicit and never turns a conflicting restaurant query into a mutation context', function () {
    Branch::factory()->for($this->organization)->for($this->brand)->create();
    session()->put('workspace.preference', ['actor' => $this->actor->id, 'branch' => $this->branch->id, 'destination' => 'overview']);
    $resolver = app(WorkspaceContextResolver::class);
    $request = workspaceRequest('restaurant.dashboard', [], ['workspace' => 'all']);
    expect($resolver->resolve($this->actor, $request)->mode)->toBe('aggregate')
        ->and($resolver->resolve($this->actor, $request)->branchId)->toBeNull();
    $request->query->set('branch', $this->branch->id);
    expect(fn () => $resolver->resolve($this->actor, $request))->toThrow(HttpException::class);
    $request->query->remove('branch');
    $request->query->set('workspace', 'arbitrary');
    expect(fn () => $resolver->resolve($this->actor, $request))->toThrow(HttpException::class);
});

test('aggregate mode never selects a mutation target even when only one branch remains', function () {
    Livewire::actingAs($this->actor)->withQueryParams(['workspace' => 'all'])
        ->test(App\Livewire\Restaurant\Dashboard::class)->assertSet('aggregate', true)->assertSet('selectedBranchId', '')
        ->call('saveOrdering')->assertHasErrors('selectedBranchId');
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
});

test('report readers reach existing analytics without acquiring export access', function () {
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $role->permissions()->detach();
    $role->permissions()->attach(Permission::query()->where('code', SystemPermission::ViewReports->value)->sole(), ['enabled' => true]);
    $member = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($member)->forRole($role)->active()->create();
    $url = route('restaurant.dashboard', ['branch' => $this->branch->id, 'workspace_section' => 'reports']);
    $this->actingAs($member)->get($url)->assertOk()->assertSee('data-navigation-key="reports"', false)->assertSee($url);
    $this->get(route('restaurant.exports.index', ['branch' => $this->branch->id]))->assertForbidden();
});

test('legacy authenticated snapshots require a fresh page before executing an operation', function () {
    $response = $this->actingAs($this->actor)->get(route('restaurant.dashboard', ['branch' => $this->branch->id]))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    $snapshot = collect($matches[1])->map(fn ($value) => json_decode(html_entity_decode($value, ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR))
        ->firstWhere('memo.name', 'restaurant.dashboard');
    unset($snapshot['memo']['workspaceActor'], $snapshot['checksum']);
    $snapshot['checksum'] = Checksum::generate($snapshot);
    $this->postJson(route('default-livewire.update'), ['components' => [[
        'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'updates' => [],
        'calls' => [['method' => 'refreshDashboard', 'params' => []]],
    ]]], ['X-Livewire' => ''])->assertStatus(409);
});
