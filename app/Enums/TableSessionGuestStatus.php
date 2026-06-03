<?php

namespace App\Enums;

enum TableSessionGuestStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
    case Left = 'left';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending approval',
            self::Active => 'Active',
            self::Rejected => 'Rejected',
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
