<?php

use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\AreaEditor;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->actor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)->for($this->actor)->forSystemRole(SystemRole::Owner)->active()->create();
});

test('creating an area publishes its canonical selected identity without recreating it on the next save', function () {
    $editor = Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id])
        ->set('form.name', 'New hall')->call('save')->assertHasNoErrors();
    $area = AreaNode::query()->where('branch_id', $this->branch->id)->sole();
    $editor->assertDispatched('floor-area-created', id: $area->id)->assertSet('areaId', $area->id)
        ->set('form.name', 'Updated hall')->call('save')->assertHasNoErrors();
    expect(AreaNode::query()->where('branch_id', $this->branch->id)->count())->toBe(1)
        ->and($area->fresh()->name)->toBe('Updated hall');
});

test('parent search uses its own page and resets after a new search without clearing the area draft', function () {
    AreaNode::factory()->for($this->branch)->count(25)->create(['name' => 'Ordinary parent', 'sort_order' => 0]);
    $needle = AreaNode::factory()->for($this->branch)->create(['name' => 'Unique needle', 'sort_order' => 0]);
    $editor = Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id])
        ->set('form.name', 'Keep my unsaved hall')->call('setPage', 2, 'parentAreasPage');
    expect($editor->viewData('parentPages')->currentPage())->toBe(2);
    $editor->set('parentSearch', 'Unique needle')->assertSet('paginators.parentAreasPage', 1)
        ->assertSet('form.name', 'Keep my unsaved hall');
    expect(array_column($editor->viewData('parents'), 'id'))->toBe([$needle->id]);
});

test('the workspace area page does not move the independent parent choices page', function () {
    AreaNode::factory()->for($this->branch)->count(25)->create();
    $editor = Livewire::actingAs($this->actor)->withQueryParams(['areasPage' => 2])
        ->test(AreaEditor::class, ['branchId' => $this->branch->id]);
    expect($editor->viewData('parentPages')->currentPage())->toBe(1)
        ->and($editor->viewData('parentPages')->getPageName())->toBe('parentAreasPage');
});

test('unavailable hierarchy choices are disabled while a corrupt area can be repaired to the top level', function () {
    $parent = AreaNode::factory()->for($this->branch)->create(['name' => 'Archived parent']);
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $parent->id, 'name' => 'Unavailable child']);
    $parent->delete();
    $editor = Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id]);
    $option = HTMLDocument::createFromString($editor->html(), LIBXML_NOERROR)->querySelector('ui-option[value="'.$child->id.'"]');
    expect($option)->not->toBeNull()->and($option->hasAttribute('disabled'))->toBeTrue();

    $editor->set('form.name', 'My retained hall')->set('form.parentId', (string) $child->id)
        ->call('save')->assertHasErrors('form.parentId')->assertSet('form.name', 'My retained hall');
    expect(AreaNode::query()->where('branch_id', $this->branch->id)->count())->toBe(1);

    $first = AreaNode::factory()->for($this->branch)->create();
    $second = AreaNode::factory()->for($this->branch)->create(['parent_id' => $first->id]);
    $first->forceFill(['parent_id' => $second->id])->save();
    Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id, 'areaId' => $first->id])
        ->set('form.parentId', '')->call('save')->assertHasNoErrors();
    expect($first->fresh()->parent_id)->toBeNull()->and($second->fresh()->parent_id)->toBe($first->id);
});

test('an unavailable ancestor during an area lifecycle mutation produces a localized error and preserves the area', function (bool $archived) {
    $parent = AreaNode::factory()->for($this->branch)->create();
    $area = AreaNode::factory()->for($this->branch)->create(['parent_id' => $parent->id]);
    if ($archived) {
        $area->delete();
    }
    $editor = Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id, 'areaId' => $area->id]);
    if (! $archived) {
        $editor->call('reviewArchive')->assertHasNoErrors();
    }
    $before = AreaNode::withTrashed()->findOrFail($area->id)->getRawOriginal();
    $parent->delete();

    $editor->call($archived ? 'restore' : 'archive')->assertHasErrors('form.parentId')
        ->assertSee(__('errors.domain.selected_parent_area_unavailable'));
    expect(AreaNode::withTrashed()->findOrFail($area->id)->getRawOriginal())->toBe($before);
})->with([false, true]);

test('an open area editor stops returning protected form data after zone management is revoked', function (string $state) {
    $area = $state === 'new' ? null : AreaNode::factory()->for($this->branch)->create(['name' => 'Protected room properties']);
    if ($state === 'archived') {
        $area->delete();
    }
    $editor = Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id, 'areaId' => $area?->id])
        ->assertOk();
    $membership = OrganizationUser::query()->where('organization_id', $this->branch->organization_id)->where('user_id', $this->actor->id)->sole();
    $membership->forceFill(['role_id' => Role::query()->where('code', SystemRole::Waiter->value)->sole()->id])->save();
    expect(Gate::forUser($this->actor->fresh())->allows('view', $this->branch))->toBeTrue()
        ->and(Gate::forUser($this->actor->fresh())->allows('manageZones', $this->branch))->toBeFalse();

    $editor->call('$refresh')->assertForbidden();
})->with(['new', 'active', 'archived']);

test('renaming an area preserves an existing absent optional icon', function () {
    $area = AreaNode::factory()->for($this->branch)->create(['name' => 'Before rename', 'icon' => null]);

    Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id, 'areaId' => $area->id])
        ->set('form.name', 'After rename')->call('save')->assertHasNoErrors();

    expect($area->fresh()->name)->toBe('After rename')->and($area->fresh()->icon)->toBeNull();
});

test('the area editor allows an explicit empty icon without changing other properties', function (string $locale) {
    app()->setLocale($locale);
    $area = AreaNode::factory()->for($this->branch)->create(['icon' => 'home']);
    $before = $area->only(['parent_id', 'name', 'type', 'sort_order', 'is_active']);

    Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id, 'areaId' => $area->id])
        ->assertSee(__('floor.no_icon'))->set('form.icon', '')->call('save')->assertHasNoErrors();

    expect($area->fresh()->icon)->toBeNull()
        ->and($area->fresh()->only(array_keys($before)))->toBe($before);
})->with(['en', 'lt', 'ru']);

test('optional area icons still reject malformed or unknown values without changing the record', function (mixed $icon) {
    $area = AreaNode::factory()->for($this->branch)->create(['icon' => 'home']);
    $before = $area->fresh()->getRawOriginal();

    Livewire::actingAs($this->actor)->test(AreaEditor::class, ['branchId' => $this->branch->id, 'areaId' => $area->id])
        ->set('form.icon', $icon)->call('save')->assertHasErrors('form.icon');

    expect($area->fresh()->getRawOriginal())->toBe($before);
})->with(['unknown icon' => ['unknown'], 'array' => [['home']], 'boolean' => [true]]);
