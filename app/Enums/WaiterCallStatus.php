<?php

namespace App\Enums;

enum WaiterCallStatus: string
{
    case Pending = 'pending';
    case Handled = 'handled';

    public function label(): string
    {
        return __(match ($this) {
            self::Pending => 'statuses.waiter_call.pending',
            self::Handled => 'statuses.waiter_call.handled',
        });
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
