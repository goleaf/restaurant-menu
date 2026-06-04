<?php

namespace App\Enums;

enum OrganizationSubscriptionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'zinc',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
