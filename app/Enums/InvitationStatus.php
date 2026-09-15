<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function localizedLabel(): string
    {
        return __(match ($this) {
            self::Pending => 'staff.invitation_statuses.pending',
            self::Accepted => 'staff.invitation_statuses.accepted',
            self::Expired => 'staff.invitation_statuses.expired',
            self::Cancelled => 'staff.invitation_statuses.cancelled',
            self::Rejected => 'staff.invitation_statuses.rejected',
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
