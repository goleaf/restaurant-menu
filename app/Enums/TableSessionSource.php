<?php

namespace App\Enums;

enum TableSessionSource: string
{
    case WaiterOpened = 'waiter_opened';
    case GuestCreated = 'guest_created';

    public function label(): string
    {
        return __(match ($this) {
            self::WaiterOpened => 'statuses.table_session_source.waiter_opened',
            self::GuestCreated => 'statuses.table_session_source.guest_created',
        });
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
