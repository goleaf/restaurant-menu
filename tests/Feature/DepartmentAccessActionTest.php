<?php

declare(strict_types=1);

use App\Actions\Departments\BuildDepartmentDashboardAction;
use App\Actions\Departments\ResolveAccessibleDepartmentIdsAction;
use App\Actions\KitchenDepartments\ResolveDefaultKitchenDepartmentAction;
use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\SystemRole;
use App\Models\Branch;
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

test('default kitchen department falls back to the first active seeded department', function (): void {
    $branch = Branch::factory()->create();
    $bar = KitchenDepartment::factory()
        ->for($branch)
        ->forType(KitchenDepartmentType::Bar)
        ->active()
        ->make();
    $seedDepartments = Mockery::mock(SeedKitchenDepartmentsForBranchAction::class);
    $seedDepartments->shouldReceive('handle')
        ->once()
        ->with($branch)
        ->andReturnUsing(function () use ($bar): void {
            $bar->saveOrFail();
        });

    $resolved = (new ResolveDefaultKitchenDepartmentAction($seedDepartments))->handle($branch);

    expect($resolved?->is($bar))->toBeTrue()
        ->and($resolved?->type)->toBe(KitchenDepartmentType::Bar);
});
