<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Support\DemoLogin\DemoAccountCatalog;

test('demo account catalogue covers every system role in canonical order', function (): void {
    $accounts = array_map(
        static fn (array $account): array => [
            'role' => $account['role']->value,
            'name' => $account['name'],
            'email' => $account['email'],
        ],
        DemoAccountCatalog::accounts(),
    );

    expect($accounts)->toBe([
        ['role' => 'superadmin', 'name' => 'Demo Superadmin', 'email' => 'superadmin@demo.test'],
        ['role' => 'owner', 'name' => 'Demo Owner', 'email' => 'owner@demo.test'],
        ['role' => 'director', 'name' => 'Demo Director', 'email' => 'director@demo.test'],
        ['role' => 'restaurant_admin', 'name' => 'Demo Restaurant Admin', 'email' => 'admin@demo.test'],
        ['role' => 'shift_manager', 'name' => 'Demo Shift Manager', 'email' => 'manager@demo.test'],
        ['role' => 'waiter', 'name' => 'Demo Waiter', 'email' => 'waiter@demo.test'],
        ['role' => 'head_chef', 'name' => 'Demo Head Chef', 'email' => 'chef@demo.test'],
        ['role' => 'cook', 'name' => 'Demo Cook', 'email' => 'cook@demo.test'],
        ['role' => 'bartender', 'name' => 'Demo Bartender', 'email' => 'bartender@demo.test'],
        ['role' => 'cashier', 'name' => 'Demo Cashier', 'email' => 'cashier@demo.test'],
        ['role' => 'accountant', 'name' => 'Demo Accountant', 'email' => 'accountant@demo.test'],
        ['role' => 'marketer', 'name' => 'Demo Marketer', 'email' => 'marketer@demo.test'],
    ])->and(array_column($accounts, 'role'))->toBe(SystemRole::values());
});

test('catalogue resolves an exact identity by role', function (): void {
    expect(DemoAccountCatalog::forRole(SystemRole::HeadChef))->toBe([
        'role' => SystemRole::HeadChef,
        'name' => 'Demo Head Chef',
        'email' => 'chef@demo.test',
    ]);
});
