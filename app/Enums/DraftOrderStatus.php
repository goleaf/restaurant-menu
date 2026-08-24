<?php

declare(strict_types=1);

namespace App\Enums;

enum DraftOrderStatus: string
{
    case Draft = 'draft';
    case SentToWaiter = 'sent_to_waiter';
    case WaiterReview = 'waiter_review';
    case Rejected = 'rejected';
    case ConvertedToOrder = 'converted_to_order';

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return in_array($next, match ($this) {
            self::Draft => [self::SentToWaiter, self::WaiterReview],
            self::SentToWaiter => [self::WaiterReview, self::Rejected, self::ConvertedToOrder],
            self::WaiterReview => [self::Rejected, self::ConvertedToOrder],
            self::Rejected => [self::Draft],
            self::ConvertedToOrder => [],
        }, true);
    }

    public function isGuestEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isWaiterEditable(): bool
    {
        return in_array($this, [self::SentToWaiter, self::WaiterReview], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::SentToWaiter => 'Sent to waiter',
            self::WaiterReview => 'Waiter review',
            self::Rejected => 'Rejected',
            self::ConvertedToOrder => 'Converted to order',
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
