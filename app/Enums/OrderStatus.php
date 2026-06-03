<?php

namespace App\Enums;

enum OrderStatus: string
{
    case ConfirmedByWaiter = 'confirmed_by_waiter';

    public function label(): string
    {
        return match ($this) {
            self::ConfirmedByWaiter => 'Confirmed by waiter',
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
