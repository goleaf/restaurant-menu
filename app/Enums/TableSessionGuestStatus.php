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
        return __(match ($this) {
            self::PendingApproval => 'statuses.table_session_guest.pending_approval',
            self::Active => 'statuses.table_session_guest.active',
            self::Rejected => 'statuses.table_session_guest.rejected',
            self::Left => 'statuses.table_session_guest.left',
            self::Removed => 'statuses.table_session_guest.removed',
        });
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
