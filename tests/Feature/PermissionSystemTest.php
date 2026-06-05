<?php

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\PermissionRole;
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

test('system permissions seeder restores the baseline and avoids duplicate pivot rows', function () {
    $this->seed(SystemRolesSeeder::class);
    $this->seed(SystemPermissionsSeeder::class);

    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => false]);

    $this->seed(SystemPermissionsSeeder::class);

    expect(Permission::query()->count())->toBe(count(SystemPermission::cases()));
    expect((bool) $role->permissions()->where('permissions.id', $permission->id)->firstOrFail()->pivot->enabled)->toBeTrue();

    $this->seed(SystemPermissionsSeeder::class);

    expect(PermissionRole::query()->count())->toBe(Role::query()->count() * Permission::query()->count());
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
});

test('required baseline permission codes have localizable UI keys', function () {
    $this->seed(SystemPermissionsSeeder::class);

    $requiredCodes = [
        'view_restaurant',
        'edit_restaurant',
        'manage_branches',
        'manage_settings',
        'manage_zones',
        'manage_service_points',
        'manage_table_sessions',
        'generate_qr',
        'manage_qr',
        'manage_menu',
        'change_prices',
        'change_availability',
        'view_orders',
        'confirm_orders',
        'edit_pending_orders',
        'cancel_orders',
        'send_to_departments',
        'mark_order_served',
        'view_department_orders',
        'mark_department_ready',
        'view_payments',
        'manage_payments',
        'correct_payments',
        'view_reports',
        'export_data',
        'manage_staff',
        'manage_permissions',
        'view_order_history',
        'view_audit_log',
    ];

    $seededCodes = Permission::query()
        ->whereIn('code', $requiredCodes)
        ->orderBy('code')
        ->pluck('code')
        ->all();

    expect($seededCodes)->toBe(collect($requiredCodes)->sort()->values()->all());

    $currentLocale = app()->getLocale();

    foreach (['en', 'ru', 'lt'] as $locale) {
        app()->setLocale($locale);

        foreach ($requiredCodes as $code) {
            $permission = SystemPermission::tryFrom($code);

            expect($permission instanceof SystemPermission)
                ->toBeTrue("Missing SystemPermission enum case for [{$code}].");

            expect(__($permission->uiLabelKey()))
                ->not->toBe($permission->uiLabelKey(), "Missing {$locale} label translation for [{$code}].");
            expect(__($permission->uiDescriptionKey()))
                ->not->toBe($permission->uiDescriptionKey(), "Missing {$locale} description translation for [{$code}].");
            expect(__($permission->uiGroupLabelKey()))
                ->not->toBe($permission->uiGroupLabelKey(), "Missing {$locale} group translation for [{$code}].");
        }
    }

    app()->setLocale($currentLocale);
});

test('fixed roles receive the prompt 443 baseline permission matrix', function () {
    $this->seed(SystemPermissionsSeeder::class);

    expect(prompt443EnabledPermissionCodesForRole(SystemRole::Superadmin))->toEqualCanonicalizing(SystemPermission::values());
    expect(prompt443EnabledPermissionCodesForRole(SystemRole::Owner))->toEqualCanonicalizing(SystemPermission::values());
    expect(prompt443EnabledPermissionCodesForRole(SystemRole::Director))->toEqualCanonicalizing(SystemPermission::values());

    $restaurantAdmin = prompt443EnabledPermissionCodesForRole(SystemRole::RestaurantAdmin);
    expect($restaurantAdmin)->toContain(
        'manage_branches',
        'manage_service_points',
        'manage_qr',
        'manage_menu',
        'change_prices',
        'manage_staff',
        'manage_permissions',
    );
    expect($restaurantAdmin)->not->toContain('manage_subscription');

    $shiftManager = prompt443EnabledPermissionCodesForRole(SystemRole::ShiftManager);
    expect($shiftManager)->toContain(
        'manage_table_sessions',
        'view_orders',
        'confirm_orders',
        'edit_pending_orders',
        'cancel_orders',
        'send_to_departments',
        'mark_order_served',
        'view_payments',
    );
    expect($shiftManager)->not->toContain('manage_payments', 'change_prices', 'manage_staff');

    $waiter = prompt443EnabledPermissionCodesForRole(SystemRole::Waiter);
    expect($waiter)->toContain(
        'view_orders',
        'confirm_orders',
        'edit_pending_orders',
        'send_to_departments',
        'mark_order_served',
        'manage_table_sessions',
    );
    expect($waiter)->not->toContain('manage_staff', 'manage_payments', 'change_prices');

    $headChef = prompt443EnabledPermissionCodesForRole(SystemRole::HeadChef);
    expect($headChef)->toContain('view_department_orders', 'mark_department_ready', 'change_availability');
    expect(prompt443EnabledPermissionCodesForRole(SystemRole::Cook))->toContain('view_department_orders', 'mark_department_ready');
    expect(prompt443EnabledPermissionCodesForRole(SystemRole::Bartender))->toContain('view_department_orders', 'mark_department_ready');

    $cashier = prompt443EnabledPermissionCodesForRole(SystemRole::Cashier);
    expect($cashier)->toContain('view_payments', 'manage_payments', 'correct_payments');
    expect($cashier)->not->toContain('manage_menu');

    $accountant = prompt443EnabledPermissionCodesForRole(SystemRole::Accountant);
    expect($accountant)->toContain('view_reports', 'view_payments', 'manage_payments', 'correct_payments', 'export_data');
    expect($accountant)->not->toContain('manage_menu');

    $marketer = prompt443EnabledPermissionCodesForRole(SystemRole::Marketer);
    expect($marketer)->toContain('manage_menu', 'change_availability');
    expect($marketer)->not->toContain('change_prices', 'manage_payments', 'correct_payments');
});

test('users inherit enabled permissions from their roles', function () {
    $this->seed(SystemRolesSeeder::class);
    $this->seed(SystemPermissionsSeeder::class);

    $user = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();

    $user->roles()->attach($role);

    expect($user->hasPermission(SystemPermission::ViewOrders))->toBeTrue();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => false]);

    expect($user->hasPermission(SystemPermission::ViewOrders))->toBeFalse();
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

/**
 * @return list<string>
 */
function prompt443EnabledPermissionCodesForRole(SystemRole $role): array
{
    return Role::query()
        ->where('code', $role->value)
        ->firstOrFail()
        ->permissions()
        ->wherePivot('enabled', true)
        ->orderBy('permissions.code')
        ->pluck('permissions.code')
        ->all();
}
