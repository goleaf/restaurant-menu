<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Menu\KitchenDepartments;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\User;
use Livewire\Livewire;

/** @return array{User, Branch, array{organizationId: int, brandId: int, branchId: int}} */
function menuInputTransportContext(): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create();
    $membership = OrganizationUser::factory()->forOrganization($organization)->forUser($owner)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $permission = Permission::factory()->create(['code' => SystemPermission::ManageMenu->value]);
    $membership->load('role')->role->permissions()->attach($permission, ['enabled' => true]);
    $branch = Branch::factory()->for($organization)->create();

    return [$owner, $branch, [
        'organizationId' => $organization->id,
        'brandId' => $branch->brand_id,
        'branchId' => $branch->id,
    ]];
}

test('kitchen department create transport validates malformed public values before persistence', function (string $field, mixed $value): void {
    [$owner, $branch, $parameters] = menuInputTransportContext();
    $component = Livewire::actingAs($owner)->test(KitchenDepartments::class, $parameters)->assertOk();
    $originalCount = $branch->kitchenDepartments()->count();
    $updates = [
        'departmentName' => 'Transport department',
        'departmentType' => 'kitchen',
        'departmentSortOrder' => 0,
        'departmentIsActive' => true,
        $field => $value,
    ];

    try {
        $component->update(
            calls: [['method' => 'createKitchenDepartment', 'params' => [], 'path' => '']],
            updates: $updates,
        )->assertHasErrors([$field]);
    } finally {
        expect($branch->kitchenDepartments()->count())->toBe($originalCount);
    }
})->with('malformed kitchen department transport');

test('kitchen department edit transport validates malformed public values before persistence', function (string $field, mixed $value): void {
    [$owner, $branch, $parameters] = menuInputTransportContext();
    $department = KitchenDepartment::factory()->for($branch)->create([
        'name' => 'Original department',
        'sort_order' => 20,
        'is_active' => false,
    ]);
    $original = $department->refresh()->getRawOriginal();
    $component = Livewire::actingAs($owner)->test(KitchenDepartments::class, $parameters)
        ->call('startEditingKitchenDepartment', $department->id)->assertHasNoErrors();
    $editingField = 'editing'.ucfirst($field);

    try {
        $component->update(
            calls: [['method' => 'updateKitchenDepartment', 'params' => [], 'path' => '']],
            updates: [$editingField => $value],
        )->assertHasErrors([$editingField]);
    } finally {
        expect($department->fresh()->getRawOriginal())->toBe($original);
    }
})->with('malformed kitchen department transport');

test('kitchen department transport accepts normal numeric strings and boolean values', function (string|bool $isActive): void {
    [$owner, $branch, $parameters] = menuInputTransportContext();

    Livewire::actingAs($owner)->test(KitchenDepartments::class, $parameters)->update(
        calls: [['method' => 'createKitchenDepartment', 'params' => [], 'path' => '']],
        updates: [
            'departmentName' => '  Numeric transport  ',
            'departmentType' => 'kitchen',
            'departmentSortOrder' => '12',
            'departmentIsActive' => $isActive,
        ],
    )->assertHasNoErrors();

    $department = $branch->kitchenDepartments()->where('name', 'Numeric transport')->sole();
    expect($department->sort_order)->toBe(12)
        ->and($department->is_active)->toBe(in_array($isActive, [true, '1'], true));
})->with(['true' => true, 'false' => false, 'numeric true' => '1', 'numeric false' => '0']);

dataset('malformed kitchen department transport', [
    'array name' => ['departmentName', ['unexpected']],
    'null name' => ['departmentName', null],
    'boolean name' => ['departmentName', true],
    'array type' => ['departmentType', ['kitchen']],
    'array sort order' => ['departmentSortOrder', [12]],
    'null sort order' => ['departmentSortOrder', null],
    'boolean sort order' => ['departmentSortOrder', true],
    'array active flag' => ['departmentIsActive', [true]],
    'null active flag' => ['departmentIsActive', null],
    'invalid string active flag' => ['departmentIsActive', 'false'],
]);
