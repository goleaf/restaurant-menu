<?php

declare(strict_types=1);

use App\Actions\Onboarding\ContinueRestaurantPreparationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\SystemPermission;
use App\Livewire\Onboarding\RestaurantSetup;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\User;
use App\Services\Onboarding\RestaurantSetupQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Selection business']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->setup = app(ContinueRestaurantPreparationAction::class)->handle($this->actor, $this->branch);
    $this->queries = app(RestaurantSetupQueryService::class);
});

it('pins a scoped selected or persisted option outside the bounded results and after a different search', function (string $kind, bool $explicit): void {
    $model = $kind === 'area' ? AreaNode::class : Menu::class;
    $method = $kind.'Options';
    $model::factory()->count(21)->for($this->branch)->sequence(fn ($sequence): array => ['name' => sprintf('Base %02d', $sequence->index)])->create();
    $chosen = $model::factory()->for($this->branch)->create(['name' => 'ZZZ current option']);
    $this->setup->forceFill([$kind === 'area' ? 'area_node_id' : 'menu_id' => $chosen->id])->save();
    $before = $this->setup->fresh()->getAttributes();
    $selected = $explicit ? (string) $chosen->id : null;
    foreach (['', 'Base'] as $search) {
        $rows = $this->queries->{$method}($this->actor, $this->setup->id, $search, $selected);
        expect($rows)->toHaveCount(21)->and($rows[$chosen->id] ?? null)->toBe($chosen->name);
    }
    expect($this->queries->{$method}($this->actor, $this->setup->id, 'No matching row', $selected))->toBe([$chosen->id => $chosen->name])
        ->and($this->setup->fresh()->getAttributes())->toBe($before);
})->with(['area', 'menu'])->with([true, false]);

it('never pins a foreign archived or malformed option', function (string $kind): void {
    $model = $kind === 'area' ? AreaNode::class : Menu::class;
    $method = $kind.'Options';
    $own = $model::factory()->for($this->branch)->create(['name' => 'Own option']);
    $foreignBranch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $foreign = $model::factory()->for($foreignBranch)->create(['name' => 'Other branch option']);
    $archived = $model::factory()->for($this->branch)->create(['name' => 'Archived option']);
    $archived->delete();
    foreach ([$foreign->id, (string) $foreign->id, $archived->id, false, true, [], ['id' => $own->id], (float) $own->id, $own->id.'tail', '-1', -1, ''] as $selected) {
        expect($this->queries->{$method}($this->actor, $this->setup->id, 'No matching row', $selected))->toBe([]);
    }
    expect($archived->fresh()->trashed())->toBeTrue();
})->with(['area', 'menu']);

it('reauthorizes current membership before returning selected options', function (string $kind): void {
    $model = $kind === 'area' ? AreaNode::class : Menu::class;
    $method = $kind.'Options';
    $chosen = $model::factory()->for($this->branch)->create();
    $this->organization->memberships()->where('user_id', $this->actor->id)->update(['status' => 'suspended']);
    expect(fn () => $this->queries->{$method}($this->actor, $this->setup->id, '', $chosen->id))->toThrow(AuthorizationException::class);
})->with(['area', 'menu']);

it('does not render a foreign parent name for a scoped room with an inconsistent parent reference', function (): void {
    $foreign = AreaNode::factory()->create(['name' => 'Other tenant parent']);
    $room = AreaNode::factory()->for($this->branch)->create(['name' => 'Local room', 'parent_id' => $foreign->id]);
    expect($this->queries->areaOptions($this->actor, $this->setup->id, '', $room->id))->toBe([$room->id => $room->name]);
});

it('does not pin a selected or linked menu after its management permission is revoked', function (): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $this->setup->forceFill(['menu_id' => $menu->id])->save();
    PermissionUserOverride::factory()->forUser($this->actor)->forOrganization($this->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail())->denied()->create();
    expect($this->queries->menuOptions($this->actor, $this->setup->id, '', $menu->id))->toBe([])
        ->and($this->queries->menuOptions($this->actor, $this->setup->id, '', null))->toBe([]);
});

it('rejects malformed selection transport before connecting a room or menu and preserves the draft', function (string $kind, mixed $value): void {
    $model = $kind === 'area' ? AreaNode::class : Menu::class;
    $model::factory()->for($this->branch)->create();
    $field = $kind === 'area' ? 'existingAreaId' : 'existingMenuId';
    $method = $kind === 'area' ? 'useExistingSpace' : 'useExistingMenu';
    $before = $this->setup->fresh()->getAttributes();

    Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->setup->id])
        ->update(calls: [['method' => $method, 'params' => [], 'path' => '']], updates: [
            $field => $value, 'form.areaName' => 'Unsaved room', 'form.itemName' => 'Unsaved dish',
        ])
        ->assertOk()->assertHasErrors($field)->assertSet($field, $value)
        ->assertSet('step', $kind === 'area' ? 2 : 3)
        ->assertSet('form.areaName', 'Unsaved room')->assertSet('form.itemName', 'Unsaved dish');

    expect($this->setup->fresh()->getAttributes())->toBe($before)
        ->and($this->setup->servicePoints()->count())->toBe(0);
})->with(['area', 'menu'])->with([
    'boolean true' => [true], 'boolean false' => [false], 'array' => [[]],
    'object shape' => [['id' => 1]], 'null' => [null], 'invalid string' => ['1tail'],
    'zero' => [0], 'fraction' => [1.5],
]);

it('connects scoped room and menu selections sent as integers or browser numeric strings', function (string $kind, bool $asString): void {
    $model = $kind === 'area' ? AreaNode::class : Menu::class;
    $selected = $model::factory()->for($this->branch)->create();
    $field = $kind === 'area' ? 'existingAreaId' : 'existingMenuId';
    $method = $kind === 'area' ? 'useExistingSpace' : 'useExistingMenu';
    $value = $asString ? (string) $selected->id : $selected->id;
    $before = $selected->fresh()->getAttributes();

    Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->setup->id])
        ->update(calls: [['method' => $method, 'params' => [], 'path' => '']], updates: [$field => $value])
        ->assertOk()->assertHasNoErrors()->assertSet($field, $value);

    expect($this->setup->fresh()->getAttribute($kind === 'area' ? 'area_node_id' : 'menu_id'))->toBe($selected->id)
        ->and($selected->fresh()->getAttributes())->toBe($before);
})->with(['area', 'menu'])->with([true, false]);
