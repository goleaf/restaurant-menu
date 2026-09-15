<?php

use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\PermissionUserOverride;
use Illuminate\Database\QueryException;

test('permission scope keeps legacy decisions and separates the same permission in two organizations', function () {
    $legacy = PermissionUserOverride::factory()->denied()->create();
    $organizations = Organization::factory()->count(2)->create();

    foreach ($organizations as $organization) {
        PermissionUserOverride::factory()->create([
            'user_id' => $legacy->user_id,
            'permission_id' => $legacy->permission_id,
            'organization_id' => $organization->id,
            'scope_key' => 'organization:'.$organization->id,
        ]);
    }

    expect($legacy->fresh()->enabled)->toBeFalse()
        ->and($legacy->fresh()->organization_id)->toBeNull()
        ->and(PermissionUserOverride::query()->where('user_id', $legacy->user_id)->count())->toBe(3);

    expect(fn () => PermissionUserOverride::factory()->create([
        'user_id' => $legacy->user_id,
        'permission_id' => $legacy->permission_id,
        'organization_id' => $organizations->first()->id,
        'scope_key' => 'organization:'.$organizations->first()->id,
    ]))->toThrow(QueryException::class);
});

test('new memberships start with a persisted access revision', function () {
    expect(OrganizationUser::factory()->create()->fresh()->access_version)->toBe(0)
        ->and(BranchUser::factory()->create()->fresh()->access_version)->toBe(0);
});

test('permission scope rollback refuses to discard scoped decisions', function () {
    $organization = Organization::factory()->create();
    $override = PermissionUserOverride::factory()->create([
        'organization_id' => $organization->id,
        'scope_key' => 'organization:'.$organization->id,
    ]);
    $migration = require database_path('migrations/2026_09_15_132159_scope_permission_overrides_to_organizations.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and($override->fresh()->organization_id)->toBe($organization->id);
});

test('legacy permission rows survive the additive scope migration roundtrip', function () {
    $legacy = PermissionUserOverride::factory()->denied()->create();
    $migration = require database_path('migrations/2026_09_15_132159_scope_permission_overrides_to_organizations.php');
    $migration->down();
    $migration->up();

    expect($legacy->fresh()->enabled)->toBeFalse()
        ->and($legacy->fresh()->scope_key)->toBe('legacy')
        ->and($legacy->fresh()->organization_id)->toBeNull();
});
