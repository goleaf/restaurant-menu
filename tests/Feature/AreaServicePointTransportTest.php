<?php

declare(strict_types=1);

use App\Enums\AreaNodeType;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\AreaEditor;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\BulkCreate;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PointEditor;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Livewire\Livewire;

/** @return array{User, Organization, Brand, Branch, AreaNode, ServicePoint} */
function areaServicePointTransportFixture(): array
{
    $user = User::factory()->create();
    $role = Role::factory()->forSystemRole(SystemRole::ShiftManager)->create();
    $organization = Organization::factory()->for($user, 'owner')->create();
    OrganizationUser::factory()->forOrganization($organization)->forUser($user)->forRole($role)->active()->create();

    foreach ([SystemPermission::ManageZones, SystemPermission::ManageServicePoints] as $systemPermission) {
        $permission = Permission::factory()->forSystemPermission($systemPermission)->create();
        $role->permissions()->attach($permission, ['enabled' => true]);
    }

    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $area = AreaNode::factory()->for($branch)->create(['name' => 'Original area']);
    $servicePoint = ServicePoint::factory()->for($branch)->for($area)->create(['name' => 'Original table']);

    return [$user, $organization, $brand, $branch, $area, $servicePoint];
}

test('area create and edit reject malformed original transport values without persistence', function (string $field, mixed $value, bool $editing): void {
    [$user, $organization, $brand, $branch, $area] = areaServicePointTransportFixture();
    $component = Livewire::actingAs($user)->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $editing ? $area->id : null]);
    $values = ['name' => 'Transport area', 'type' => AreaNodeType::Hall->value, 'icon' => 'squares-2x2', 'parentId' => '', 'sortOrder' => '12', 'isActive' => '1'];

    $values = collect($values)->mapWithKeys(fn (mixed $item, string $key): array => ['form.'.$key => $item])->all();

    $component->update(updates: $values)->assertOk();
    $count = AreaNode::query()->count();
    $original = $area->fresh()->getRawOriginal();
    $component->update(calls: [['method' => 'save', 'params' => [], 'path' => '']], updates: ['form.'.$field => $value])->assertHasErrors(['form.'.$field]);

    expect(AreaNode::query()->count())->toBe($count)->and($area->fresh()->getRawOriginal())->toBe($original);
})->with([
    'array name' => ['name', ['invalid']], 'boolean name' => ['name', true], 'null type' => ['type', null], 'boolean icon' => ['icon', true],
    'boolean parent' => ['parentId', true], 'array parent' => ['parentId', ['invalid']], 'boolean sort order' => ['sortOrder', true],
    'fractional sort order' => ['sortOrder', '1.5'], 'invalid active' => ['isActive', 'false'],
])->with(['create' => false, 'edit' => true]);

test('area create and edit preserve valid numeric strings and booleans', function (bool $editing): void {
    [$user, $organization, $brand, $branch, $area] = areaServicePointTransportFixture();
    $component = Livewire::actingAs($user)->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $editing ? $area->id : null]);
    $values = ['name' => '  Transport area  ', 'type' => 'hall', 'icon' => 'squares-2x2', 'parentId' => '', 'sortOrder' => '12', 'isActive' => false];
    $values = collect($values)->mapWithKeys(fn (mixed $item, string $key): array => ['form.'.$key => $item])->all();
    $count = AreaNode::query()->count();
    $component->update(updates: $values)->call('save')->assertHasNoErrors();
    $saved = $editing ? $area->fresh() : AreaNode::query()->latest('id')->firstOrFail();
    expect(AreaNode::query()->count())->toBe($count + ($editing ? 0 : 1))->and($saved->name)->toBe('Transport area')->and($saved->sort_order)->toBe(12)->and($saved->is_active)->toBeFalse();
})->with(['create' => false, 'edit' => true]);

test('service point create and edit reject malformed original transport values without persistence', function (string $field, mixed $value, bool $editing): void {
    [$user, $organization, $brand, $branch, $area, $servicePoint] = areaServicePointTransportFixture();
    $component = Livewire::actingAs($user)->test(PointEditor::class, ['branchId' => $branch->id, 'pointId' => $editing ? $servicePoint->id : null]);
    $values = ['areaNodeId' => (string) $area->id, 'type' => ServicePointType::Table->value, 'icon' => 'squares-2x2', 'name' => 'Transport table', 'displayNumber' => 'T12', 'capacity' => '4', 'isActive' => '1'];
    $values = collect($values)->mapWithKeys(fn (mixed $item, string $key): array => ['form.'.$key => $item])->all();
    $component->update(updates: $values)->assertOk();
    $count = ServicePoint::query()->count();
    $original = $servicePoint->fresh()->getRawOriginal();
    $component->update(calls: [['method' => 'save', 'params' => [], 'path' => '']], updates: ['form.'.$field => $value])->assertHasErrors(['form.'.$field]);
    expect(ServicePoint::query()->count())->toBe($count)->and($servicePoint->fresh()->getRawOriginal())->toBe($original);
})->with([
    'array area' => ['areaNodeId', ['invalid']], 'boolean area' => ['areaNodeId', true], 'array type' => ['type', ['table']],
    'null name' => ['name', null], 'boolean name' => ['name', true], 'boolean display number' => ['displayNumber', true],
    'boolean capacity' => ['capacity', true], 'fractional capacity' => ['capacity', '2.5'], 'invalid active' => ['isActive', 'false'],
])->with(['create' => false, 'edit' => true]);

test('service point create and edit preserve valid numeric strings and booleans', function (bool $editing): void {
    [$user, $organization, $brand, $branch, $area, $servicePoint] = areaServicePointTransportFixture();
    $component = Livewire::actingAs($user)->test(PointEditor::class, ['branchId' => $branch->id, 'pointId' => $editing ? $servicePoint->id : null]);
    $values = ['areaNodeId' => (string) $area->id, 'type' => 'table', 'icon' => 'squares-2x2', 'name' => '  Transport table  ', 'displayNumber' => '  T12  ', 'capacity' => '4', 'isActive' => false];
    $values = collect($values)->mapWithKeys(fn (mixed $item, string $key): array => ['form.'.$key => $item])->all();
    $count = ServicePoint::query()->count();
    $component->update(updates: $values)->call('save')->assertHasNoErrors();
    $saved = $editing ? $servicePoint->fresh() : ServicePoint::query()->latest('id')->firstOrFail();
    expect(ServicePoint::query()->count())->toBe($count + ($editing ? 0 : 1))->and($saved->name)->toBe('Transport table')->and($saved->display_number)->toBe('T12')->and($saved->capacity)->toBe(4)->and($saved->is_active)->toBeFalse();
})->with(['create' => false, 'edit' => true]);

test('bulk preview rejects malformed transport and wrong branch areas without persistence', function (string $field, mixed $value): void {
    [$user, $organization, $brand, $branch] = areaServicePointTransportFixture();
    $component = Livewire::actingAs($user)->test(BulkCreate::class, ['branchId' => $branch->id]);
    $component->update(updates: ['form.bulkType' => 'table', 'form.bulkPrefix' => 'T', 'form.bulkFrom' => '1', 'form.bulkTo' => '2', 'form.bulkCapacity' => '4'])->assertOk();
    $count = ServicePoint::query()->count();
    $component->update(calls: [['method' => 'review', 'params' => [], 'path' => '']], updates: ['form.'.$field => $value])->assertHasErrors(['form.'.$field]);
    expect(ServicePoint::query()->count())->toBe($count);
})->with([
    'array area' => ['areaNodeId', ['invalid']], 'boolean area' => ['areaNodeId', true], 'boolean prefix' => ['bulkPrefix', true],
    'boolean from' => ['bulkFrom', true], 'fractional from' => ['bulkFrom', '1.5'], 'array to' => ['bulkTo', ['2']],
    'fractional capacity' => ['bulkCapacity', '2.5'],
]);

test('bulk confirm validates malformed original transport when preview readiness is forged', function (string $field, mixed $value): void {
    [$user, $organization, $brand, $branch, $area] = areaServicePointTransportFixture();
    $component = Livewire::actingAs($user)->test(BulkCreate::class, ['branchId' => $branch->id]);
    $component->update(updates: ['form.areaNodeId' => (string) $area->id, 'form.bulkType' => 'table', 'form.bulkPrefix' => 'T', 'form.bulkFrom' => '1', 'form.bulkTo' => '2', 'form.bulkCapacity' => '4'])->call('review')->assertHasNoErrors();
    $count = ServicePoint::query()->count();

    $component->update(updates: ['form.'.$field => $value])->assertSet('form.'.$field, $value)->assertSet('fingerprint', '');
    expect(fn () => $component->set('fingerprint', 'forged-review'))->toThrow(CannotUpdateLockedPropertyException::class);
    $component->update(calls: [['method' => 'apply', 'params' => [], 'path' => '']])
        ->assertHasErrors(['form.'.$field]);

    expect(ServicePoint::query()->count())->toBe($count);
})->with([
    'array area' => ['areaNodeId', ['invalid']],
    'boolean from' => ['bulkFrom', true],
    'fractional to' => ['bulkTo', '2.5'],
    'boolean capacity' => ['bulkCapacity', true],
]);

test('bulk preview and forged confirm reject a range of 201 without persistence', function (string $action): void {
    [$user, $organization, $brand, $branch, $area] = areaServicePointTransportFixture();
    $component = Livewire::actingAs($user)->test(BulkCreate::class, ['branchId' => $branch->id]);
    $component->update(updates: ['form.areaNodeId' => (string) $area->id, 'form.bulkType' => 'table', 'form.bulkPrefix' => 'T', 'form.bulkFrom' => '1', 'form.bulkTo' => '201', 'form.bulkCapacity' => '4'])->assertOk();
    $count = ServicePoint::query()->count();

    if ($action === 'apply') {
        expect(fn () => $component->set('fingerprint', 'forged-review'))->toThrow(CannotUpdateLockedPropertyException::class);
    }

    $component->update(calls: [['method' => $action, 'params' => [], 'path' => '']])->assertHasErrors(['form.bulkTo']);
    expect(ServicePoint::query()->count())->toBe($count);
})->with(['review', 'apply']);

test('bulk preview and confirm preserve valid numeric strings and reject a foreign area', function (): void {
    [$user, $organization, $brand, $branch, $area] = areaServicePointTransportFixture();
    $foreignBranch = Branch::factory()->create();
    $foreignArea = AreaNode::factory()->for($foreignBranch)->create();
    $component = Livewire::actingAs($user)->test(BulkCreate::class, ['branchId' => $branch->id]);
    $count = ServicePoint::query()->where('branch_id', $branch->id)->count();
    $component->update(calls: [['method' => 'review', 'params' => [], 'path' => ''], ['method' => 'apply', 'params' => [], 'path' => '']], updates: ['form.areaNodeId' => (string) $area->id, 'form.bulkType' => 'table', 'form.bulkPrefix' => ' T ', 'form.bulkFrom' => '1', 'form.bulkTo' => '2', 'form.bulkCapacity' => '4'])->assertHasNoErrors();
    expect(ServicePoint::query()->where('branch_id', $branch->id)->count())->toBe($count + 2);
    $component->update(calls: [['method' => 'review', 'params' => [], 'path' => '']], updates: ['form.areaNodeId' => (string) $foreignArea->id])->assertHasErrors(['form.areaNodeId']);
});
