<?php

namespace App\Enums;

enum OrganizationUserStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';
    case Removed = 'removed';

    public function localizedLabel(): string
    {
        return __(match ($this) {
            self::Active => 'staff.statuses.active',
            self::Invited => 'staff.statuses.invited',
            self::Suspended => 'staff.statuses.suspended',
            self::Removed => 'staff.statuses.removed',
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
