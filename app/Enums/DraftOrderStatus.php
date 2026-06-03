<?php

namespace App\Enums;

enum DraftOrderStatus: string
{
    case Draft = 'draft';
    case SentToWaiter = 'sent_to_waiter';
    case WaiterReview = 'waiter_review';
    case Rejected = 'rejected';
    case ConvertedToOrder = 'converted_to_order';

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
