<?php

declare(strict_types=1);

use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Availability\Index;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->actor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)
        ->forUser($this->actor)->forSystemRole(SystemRole::Owner)->active()->create();
});

function workspaceBoundarySnapshot(TestResponse $response, string $component): string
{
    $response->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    $snapshot = collect($matches[1])
        ->map(fn (string $encoded): string => html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5))
        ->first(fn (string $value): bool => json_decode($value, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component);

    expect($snapshot)->toBeString()->not->toBeEmpty();

    return $snapshot;
}

/** @return array{components: list<array{snapshot: string, updates: array<string, mixed>, calls: list<array{method: string, params: list<mixed>}>}>} */
function workspaceBoundaryCall(string $snapshot, string $method, array $parameters = [], array $updates = []): array
{
    return ['components' => [[
        'snapshot' => $snapshot,
        'updates' => $updates,
        'calls' => [['method' => $method, 'params' => $parameters]],
    ]]];
}

test('a signed ordinary workspace snapshot rejects a different authorized actor before its action', function (string $screen): void {
    $secondActor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)
        ->forUser($secondActor)->forSystemRole(SystemRole::Owner)->active()->create();
    $menu = Menu::factory()->forBranch($this->branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
    $parameters = [$this->branch->organization_id, $this->branch->brand_id, $this->branch->id];
    $component = $screen === 'menu.availability' ? app('livewire.factory')->resolveComponentName(Index::class) : 'organizations.brands.branches.staff';
    $route = $screen === 'menu.availability' ? 'organizations.brands.branches.availability.index' : 'organizations.brands.branches.staff.index';
    if ($screen === 'menu.availability') {
        $parameters['section'] = 'stoplist';
    }
    $snapshot = workspaceBoundarySnapshot($this->actingAs($this->actor)->get(route($route, $parameters)), $component);
    $method = $screen === 'menu.availability' ? 'openItem' : 'openInvitation';
    $arguments = $screen === 'menu.availability' ? [$item->id] : [];

    $response = $this->actingAs($secondActor)->postJson(route('default-livewire.update'), workspaceBoundaryCall($snapshot, $method, $arguments), ['X-Livewire' => '']);
    expect([$response->status(), $item->fresh()->is_available])->toBe([409, true]);
    expect(json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['workspaceActor'] ?? null)->toBe($this->actor->id);

    expect($item->fresh()->is_available)->toBeTrue()->and(Invitation::query()->count())->toBe(0);
})->with(['menu.availability', 'staff.index']);

test('untrusted workspace mode and destination cannot replace signed locked state', function (string $property, string $value): void {
    $snapshot = workspaceBoundarySnapshot($this->actingAs($this->actor)->get(route('restaurant.dashboard', ['branch' => $this->branch->id])), 'workspace.restaurant-switcher');
    $preference = ['actor' => $this->actor->id, 'branch' => $this->branch->id, 'destination' => 'overview'];
    $this->withSession(['workspace.preference' => $preference]);
    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson(route('default-livewire.update'), workspaceBoundaryCall($snapshot, 'remember', updates: [$property => $value]), ['X-Livewire' => '']))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(session('workspace.preference'))->toBe($preference);
})->with([
    ['mode', 'arbitrary'],
    ['mode', 'aggregate'],
    ['destination', 'superadmin'],
    ['destination', 'reports'],
]);

test('aggregate scope cannot replace a concrete restaurant resource route', function (string $suffix): void {
    $this->actingAs($this->actor)->get(route('organizations.brands.branches.'.$suffix, [
        $this->branch->organization_id, $this->branch->brand_id, $this->branch->id, 'workspace' => 'all',
    ]))->assertStatus(409);

    expect(session('workspace.preference'))->toBeNull();
})->with(['menu.index', 'staff.index', 'settings.index']);

test('a revoked selected restaurant cannot be remembered by its old signed snapshot', function (): void {
    $other = Branch::factory()->for($this->branch->organization)->for($this->branch->brand)->create();
    $membership = BranchUser::factory()->forBranch($this->branch)->forUser($this->actor)->active()->create();
    BranchUser::factory()->forBranch($other)->forUser($this->actor)->active()->create();
    $snapshot = workspaceBoundarySnapshot($this->actingAs($this->actor)->get(route('restaurant.dashboard', ['branch' => $this->branch->id])), 'workspace.restaurant-switcher');
    $preference = ['actor' => $this->actor->id, 'branch' => $other->id, 'destination' => 'overview'];
    $this->withSession(['workspace.preference' => $preference]);
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->saveOrFail();

    $this->postJson(route('default-livewire.update'), workspaceBoundaryCall($snapshot, 'remember'), ['X-Livewire' => ''])
        ->assertForbidden();
    expect(session('workspace.preference'))->toBe($preference);
    $this->get(route('restaurant.dashboard', ['branch' => $this->branch->id]))->assertForbidden();
    $this->get(route('restaurant.dashboard', ['branch' => $other->id]))->assertOk();
});

test('head and prefetched concrete pages leave the saved workspace unchanged', function (string $method, array $headers): void {
    $other = Branch::factory()->for($this->branch->organization)->for($this->branch->brand)->create();
    $preference = ['actor' => $this->actor->id, 'branch' => $other->id, 'destination' => 'team'];
    $this->actingAs($this->actor)->withSession(['workspace.preference' => $preference]);
    $url = route('organizations.brands.branches.menu.index', [$this->branch->organization_id, $this->branch->brand_id, $this->branch->id]);

    $this->withHeaders($headers)->call($method, $url)->assertOk()->assertSessionHas('workspace.preference', $preference);
})->with([
    'head' => ['HEAD', []],
    'prefetch' => ['GET', ['Purpose' => 'prefetch', 'Sec-Purpose' => 'prefetch']],
    'navigate prefetch' => ['GET', ['X-Livewire-Navigate' => '', 'Purpose' => 'prefetch']],
]);

test('a narrow permission account enters its allowed task without overview access', function (SystemPermission $permission, string $routeName, array $extra): void {
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $role->permissions()->detach();
    $role->permissions()->attach(Permission::query()->where('code', $permission->value)->firstOrFail(), ['enabled' => true]);
    $recipient = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)->forUser($recipient)->forRole($role)->active()->create();
    $parameters = str_starts_with($routeName, 'organizations.')
        ? [$this->branch->organization_id, $this->branch->brand_id, $this->branch->id, ...$extra]
        : ['branch' => $this->branch->id, ...$extra];
    $url = route($routeName, $parameters);

    $this->actingAs($recipient)->get(route('dashboard'))->assertRedirect($url);
    $this->get($url)->assertOk();
    $this->get(route('restaurant.dashboard', ['branch' => $this->branch->id]))->assertForbidden();
})->with([
    [SystemPermission::ManageStaff, 'organizations.brands.branches.staff.index', []],
    [SystemPermission::ManageSettings, 'organizations.brands.branches.availability.index', []],
    [SystemPermission::ChangeAvailability, 'organizations.brands.branches.availability.index', []],
    [SystemPermission::ExportData, 'restaurant.exports.index', []],
    [SystemPermission::ViewAuditLog, 'restaurant.audit-log.index', []],
]);

test('aggregate state and absent mutation target cannot be changed through a signed update', function (string $property, mixed $value): void {
    $snapshot = workspaceBoundarySnapshot($this->actingAs($this->actor)->get(route('restaurant.dashboard', ['workspace' => 'all'])), 'restaurant.dashboard');
    $state = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['data'];
    expect($state['aggregate'])->toBeTrue()->and($state['selectedBranchId'])->toBe('');
    $this->withoutExceptionHandling();
    $value = $property === 'selectedBranchId' ? (string) $this->branch->id : $value;

    expect(fn () => $this->postJson(route('default-livewire.update'), workspaceBoundaryCall($snapshot, 'refreshDashboard', updates: [
        $property => $value,
    ]), ['X-Livewire' => '']))->toThrow(CannotUpdateLockedPropertyException::class);
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
})->with([['aggregate', false], ['selectedBranchId', 'selected']]);

test('aggregate choice and preference retain an explicit task without selecting a restaurant', function (): void {
    $snapshot = workspaceBoundarySnapshot($this->actingAs($this->actor)->get(route('restaurant.exports.index', ['branch' => $this->branch->id])), 'workspace.restaurant-switcher');
    $response = $this->postJson(route('default-livewire.update'), workspaceBoundaryCall($snapshot, 'chooseAggregate'), ['X-Livewire' => ''])->assertOk();
    $url = route('restaurant.exports.index', ['workspace' => 'all']);
    expect($response->json('components.0.effects.redirect'))->toBe($url)->and(session('workspace.preference'))->toBeNull();
    $aggregate = workspaceBoundarySnapshot($this->get($url), 'workspace.restaurant-switcher');
    $this->postJson(route('default-livewire.update'), workspaceBoundaryCall($aggregate, 'remember'), ['X-Livewire' => ''])->assertOk();
    expect(session('workspace.preference'))->toBe(['actor' => $this->actor->id, 'mode' => 'aggregate', 'destination' => 'reports']);
    $this->get(route('dashboard'))->assertRedirect($url);
});

test('one remaining restaurant still exposes the aggregate audit scope selector', function (): void {
    $response = $this->actingAs($this->actor)->get(route('restaurant.audit-log.index', ['branch' => $this->branch->id]))->assertOk();
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);

    expect($document->querySelector('[data-workspace-restaurant] .workspace-restaurant__trigger'))->not->toBeNull();
    expect($document->querySelector('[data-workspace-restaurant] [wire\\:click="chooseAggregate"]'))->not->toBeNull();
});

test('a revoked ordering target never falls back to the other accessible restaurant after a failed save', function (): void {
    $other = Branch::factory()->for($this->branch->organization)->for($this->branch->brand)->create();
    $membership = BranchUser::factory()->forBranch($this->branch)->forUser($this->actor)->active()->create();
    BranchUser::factory()->forBranch($other)->forUser($this->actor)->active()->create();
    $snapshot = workspaceBoundarySnapshot($this->actingAs($this->actor)->get(route('organizations.brands.branches.availability.index', [$this->branch->organization_id, $this->branch->brand_id, $this->branch->id])), app('livewire.factory')->resolveComponentName(Index::class));
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->saveOrFail();

    $response = $this->postJson(route('default-livewire.update'), workspaceBoundaryCall($snapshot, 'applyPause', updates: [
        'pause.mode' => 'indefinite',
        'pause.reason' => 'Revoked restaurant must remain unchanged',
    ]), ['X-Livewire' => '']);

    expect([$this->branch->fresh()->is_temporarily_closed, $other->fresh()->is_temporarily_closed])->toBe([false, false]);
    $response->assertForbidden();
});

test('a transient dashboard read failure retains its locked target for the next authorization check', function (): void {
    $other = Branch::factory()->for($this->branch->organization)->for($this->branch->brand)->create();
    $membership = BranchUser::factory()->forBranch($this->branch)->forUser($this->actor)->active()->create();
    BranchUser::factory()->forBranch($other)->forUser($this->actor)->active()->create();
    $snapshot = workspaceBoundarySnapshot($this->actingAs($this->actor)->get(route('restaurant.dashboard', ['branch' => $this->branch->id])), 'restaurant.dashboard');
    $this->mock(BuildRestaurantDashboardAction::class)
        ->shouldReceive('handle')->once()->andThrow(new RuntimeException('Isolated workspace read failure'));

    $failure = $this->postJson(route('default-livewire.update'), workspaceBoundaryCall($snapshot, '$refresh'), ['X-Livewire' => ''])->assertOk();
    $retrySnapshot = $failure->json('components.0.snapshot');
    $state = json_decode($retrySnapshot, true, flags: JSON_THROW_ON_ERROR)['data'];
    expect($state['selectedBranchId'])->toBe((string) $this->branch->id)
        ->and($state['canAccessRestaurantDashboard'])->toBeFalse();

    app()->forgetInstance(BuildRestaurantDashboardAction::class);
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->saveOrFail();
    $this->postJson(route('default-livewire.update'), workspaceBoundaryCall($retrySnapshot, '$refresh'), ['X-Livewire' => ''])->assertForbidden();
    expect([$this->branch->fresh()->is_temporarily_closed, $other->fresh()->is_temporarily_closed])->toBe([false, false]);
});
