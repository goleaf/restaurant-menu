<?php

declare(strict_types=1);

namespace App\Enums;

enum KitchenTicketItemStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return in_array($next, match ($this) {
            self::New => [self::Accepted, self::InProgress, self::Ready, self::Cancelled],
            self::Accepted => [self::InProgress, self::Ready, self::Cancelled],
            self::InProgress => [self::Ready, self::Cancelled],
            self::Ready, self::Cancelled => [],
        }, true);
    }

    public function label(): string
    {
        return __($this->translationKey());
    }

    public function translationKey(): string
    {
        return match ($this) {
            self::New => 'statuses.kitchen_ticket_item.new',
            self::Accepted => 'statuses.kitchen_ticket_item.accepted',
            self::InProgress => 'statuses.kitchen_ticket_item.in_progress',
            self::Ready => 'statuses.kitchen_ticket_item.ready',
            self::Cancelled => 'statuses.kitchen_ticket_item.cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::New => 'rose',
            self::Accepted => 'blue',
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
