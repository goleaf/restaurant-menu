<?php

use App\Enums\SystemRole;
use App\Models\Role;
use Database\Seeders\SystemRolesSeeder;

test('system roles are seeded from the fixed enum list', function () {
    $this->seed(SystemRolesSeeder::class);

    $roles = Role::query()
        ->select(['code', 'name', 'sort_order'])
        ->orderBy('sort_order')
        ->get();

    expect($roles)->toHaveCount(count(SystemRole::cases()));
    expect($roles->pluck('code')->map->value->all())->toBe(SystemRole::values());
    expect($roles->pluck('name')->all())->toBe(SystemRole::labels());
});

test('system roles seeder is idempotent', function () {
    $this->seed(SystemRolesSeeder::class);
    $this->seed(SystemRolesSeeder::class);

    expect(Role::query()->count())->toBe(count(SystemRole::cases()));
});

test('database seeder includes system roles', function () {
    $this->seed();

    expect(Role::query()->count())->toBe(count(SystemRole::cases()));
});

test('custom role codes cannot be stored through the role model', function () {
    expect(fn () => Role::query()->create([
        'code' => 'custom_role',
        'name' => 'Custom role',
        'sort_order' => 99,
    ]))->toThrow(ValueError::class);
});
