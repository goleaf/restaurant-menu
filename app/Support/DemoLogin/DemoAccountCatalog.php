<?php

declare(strict_types=1);

namespace App\Support\DemoLogin;

use App\Enums\SystemRole;

final class DemoAccountCatalog
{
    /**
     * @var array<string, array{name: string, email: string}>
     */
    private const array IDENTITIES = [
        'superadmin' => ['name' => 'Demo Superadmin', 'email' => 'superadmin@demo.test'],
        'owner' => ['name' => 'Demo Owner', 'email' => 'owner@demo.test'],
        'director' => ['name' => 'Demo Director', 'email' => 'director@demo.test'],
        'restaurant_admin' => ['name' => 'Demo Restaurant Admin', 'email' => 'admin@demo.test'],
        'shift_manager' => ['name' => 'Demo Shift Manager', 'email' => 'manager@demo.test'],
        'waiter' => ['name' => 'Demo Waiter', 'email' => 'waiter@demo.test'],
        'head_chef' => ['name' => 'Demo Head Chef', 'email' => 'chef@demo.test'],
        'cook' => ['name' => 'Demo Cook', 'email' => 'cook@demo.test'],
        'bartender' => ['name' => 'Demo Bartender', 'email' => 'bartender@demo.test'],
        'cashier' => ['name' => 'Demo Cashier', 'email' => 'cashier@demo.test'],
        'accountant' => ['name' => 'Demo Accountant', 'email' => 'accountant@demo.test'],
        'marketer' => ['name' => 'Demo Marketer', 'email' => 'marketer@demo.test'],
    ];

    /**
     * @return list<array{role: SystemRole, name: string, email: string}>
     */
    public static function all(): array
    {
        return array_map(
            static fn (SystemRole $role): array => self::forRole($role),
            SystemRole::cases(),
        );
    }

    /**
     * @return array{role: SystemRole, name: string, email: string}
     */
    public static function forRole(SystemRole $role): array
    {
        $identity = self::IDENTITIES[$role->value];

        return [
            'role' => $role,
            'name' => $identity['name'],
            'email' => $identity['email'],
        ];
    }
}
