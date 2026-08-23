<?php

declare(strict_types=1);

use App\Actions\Departments\BuildDepartmentDashboardAction;
use App\Actions\Departments\ResolveAccessibleDepartmentIdsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\SystemRole;
use App\Models\KitchenDepartment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

test('department actions report access from the resolved active departments', function (): void {
    $this->seed(SystemPermissionsSeeder::class);

    KitchenDepartment::factory()->active()->create([
        'type' => KitchenDepartmentType::Kitchen,
    ]);
    $superadmin = User::factory()->create();
    $superadmin->roles()->attach(
        Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail(),
    );
    $outsider = User::factory()->create();
    $departmentTypes = [KitchenDepartmentType::Kitchen];
    $resolveAccess = app(ResolveAccessibleDepartmentIdsAction::class);
    $buildDashboard = app(BuildDepartmentDashboardAction::class);

    expect($resolveAccess->userHasAccess($superadmin, $departmentTypes, [], []))->toBeTrue()
        ->and($resolveAccess->userHasAccess($outsider, $departmentTypes, [], []))->toBeFalse()
        ->and($buildDashboard->userHasAccess($superadmin, $departmentTypes, [], []))->toBeTrue()
        ->and($buildDashboard->userHasAccess($outsider, $departmentTypes, [], []))->toBeFalse();
});
