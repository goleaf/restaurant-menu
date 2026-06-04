<?php

namespace App\Enums;

enum WaiterCallStatus: string
{
    case Pending = 'pending';
    case Handled = 'handled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for waiter',
            self::Handled => 'Handled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'orange',
            self::Handled => 'emerald',
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
