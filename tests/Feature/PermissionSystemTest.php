<?php

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Database\Seeders\SystemRolesSeeder;

test('system permissions are seeded from the base enum list', function () {
    $this->seed(SystemPermissionsSeeder::class);

    $permissions = Permission::query()
        ->select(['code', 'name', 'sort_order'])
        ->orderBy('sort_order')
        ->get();

    expect($permissions)->toHaveCount(count(SystemPermission::cases()));
    expect($permissions->pluck('code')->all())->toBe(SystemPermission::values());
    expect($permissions->pluck('name')->all())->toBe(SystemPermission::labels());
});

test('system permissions seeder is idempotent and keeps role toggles', function () {
    $this->seed(SystemRolesSeeder::class);
    $this->seed(SystemPermissionsSeeder::class);

    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);

    $this->seed(SystemPermissionsSeeder::class);

    expect(Permission::query()->count())->toBe(count(SystemPermission::cases()));
    expect((bool) $role->permissions()->where('permissions.id', $permission->id)->firstOrFail()->pivot->enabled)->toBeTrue();
});

test('each fixed role receives a toggle row for every base permission', function () {
    $this->seed(SystemRolesSeeder::class);
    $this->seed(SystemPermissionsSeeder::class);

    $role = Role::query()->where('code', SystemRole::ShiftManager->value)->firstOrFail();

    $permissionStates = $role->permissions()
        ->select(['permissions.id', 'permissions.code'])
        ->orderBy('permissions.sort_order')
        ->get();

    expect($permissionStates)->toHaveCount(count(SystemPermission::cases()));
    expect($permissionStates->pluck('code')->all())->toBe(SystemPermission::values());
    expect($permissionStates->pluck('pivot.enabled')->map(fn ($enabled) => (bool) $enabled)->every(fn (bool $enabled) => $enabled === false))->toBeTrue();
});

test('users inherit enabled permissions from their roles', function () {
    $this->seed(SystemRolesSeeder::class);
    $this->seed(SystemPermissionsSeeder::class);

    $user = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();

    $user->roles()->attach($role);

    expect($user->hasPermission(SystemPermission::ViewOrders))->toBeFalse();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);

    expect($user->hasPermission(SystemPermission::ViewOrders))->toBeTrue();
});

test('user permission overrides win over role permissions', function () {
    $this->seed(SystemRolesSeeder::class);
    $this->seed(SystemPermissionsSeeder::class);

    $user = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ConfirmOrders->value)->firstOrFail();

    $user->roles()->attach($role);
    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);

    expect($user->hasPermission(SystemPermission::ConfirmOrders))->toBeTrue();

    $user->permissionOverrides()->attach($permission, ['enabled' => false]);

    expect($user->hasPermission(SystemPermission::ConfirmOrders))->toBeFalse();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => false]);
    $user->permissionOverrides()->updateExistingPivot($permission->id, ['enabled' => true]);

    expect($user->hasPermission(SystemPermission::ConfirmOrders))->toBeTrue();
});

test('database seeder includes roles and base permissions', function () {
    $this->seed();

    expect(Role::query()->count())->toBe(count(SystemRole::cases()));
    expect(Permission::query()->count())->toBe(count(SystemPermission::cases()));
});
