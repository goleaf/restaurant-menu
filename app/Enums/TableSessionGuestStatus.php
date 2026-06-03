<?php

namespace App\Enums;

enum TableSessionGuestStatus: string
{
    case Active = 'active';
    case PendingApproval = 'pending_approval';
    case Left = 'left';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::PendingApproval => 'Pending approval',
            self::Left => 'Left',
            self::Removed => 'Removed',
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
