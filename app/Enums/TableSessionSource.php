<?php

namespace App\Enums;

enum TableSessionSource: string
{
    case WaiterOpened = 'waiter_opened';
    case GuestCreated = 'guest_created';

    public function label(): string
    {
        return match ($this) {
            self::WaiterOpened => 'Waiter opened',
            self::GuestCreated => 'Guest created',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $source): string => $source->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $source): array => [$source->value => $source->label()])
            ->all();
    }
}
