<?php

declare(strict_types=1);

namespace App\Enums;

enum KitchenTicketItemStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In progress',
            self::Ready => 'Ready',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::New => 'rose',
            self::InProgress => 'amber',
            self::Ready => 'emerald',
            self::Cancelled => 'zinc',
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
            ->reject(fn (self $status): bool => $status === self::Cancelled)
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
