<?php

namespace App\Enums;

enum KitchenTicketItemStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In progress',
            self::Ready => 'Ready',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::New => 'rose',
            self::InProgress => 'amber',
            self::Ready => 'emerald',
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

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
