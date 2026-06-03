<?php

namespace App\Enums;

enum OrderStatus: string
{
    case ConfirmedByWaiter = 'confirmed_by_waiter';
    case SentToKitchenBar = 'sent_to_kitchen_bar';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Served = 'served';
    case PaymentRequested = 'payment_requested';
    case Paid = 'paid';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ConfirmedByWaiter => 'Confirmed by waiter',
            self::SentToKitchenBar => 'Sent to kitchen/bar',
            self::InProgress => 'In progress',
            self::Ready => 'Ready',
            self::Served => 'Served',
            self::PaymentRequested => 'Payment requested',
            self::Paid => 'Paid',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
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
