<?php

namespace App\Enums;

enum TableSessionJoinRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return __(match ($this) {
            self::Pending => 'statuses.table_session_join_request.pending',
            self::Approved => 'statuses.table_session_join_request.approved',
            self::Rejected => 'statuses.table_session_join_request.rejected',
            self::Expired => 'statuses.table_session_join_request.expired',
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
