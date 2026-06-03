<?php

namespace App\Enums;

enum SystemRole: string
{
    case Superadmin = 'superadmin';
    case Owner = 'owner';
    case Director = 'director';
    case RestaurantAdmin = 'restaurant_admin';
    case ShiftManager = 'shift_manager';
    case Waiter = 'waiter';
    case HeadChef = 'head_chef';
    case Cook = 'cook';
    case Bartender = 'bartender';
    case Cashier = 'cashier';
    case Accountant = 'accountant';
    case Marketer = 'marketer';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Owner => 'Owner',
            self::Director => 'Director',
            self::RestaurantAdmin => 'Restaurant administrator',
            self::ShiftManager => 'Shift manager',
            self::Waiter => 'Waiter',
            self::HeadChef => 'Head chef',
            self::Cook => 'Cook',
            self::Bartender => 'Bartender',
            self::Cashier => 'Cashier',
            self::Accountant => 'Accountant',
            self::Marketer => 'Marketer',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_map(
            fn (self $role): string => $role->label(),
            self::cases(),
        );
    }

    /**
     * @return array<int, array{code: string, name: string, sort_order: int}>
     */
    public static function seedRows(): array
    {
        return array_map(
            fn (self $role, int $index): array => [
                'code' => $role->value,
                'name' => $role->label(),
                'sort_order' => $index + 1,
            ],
            self::cases(),
            array_keys(self::cases()),
        );
    }
}
