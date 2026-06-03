<?php

namespace App\Enums;

enum TableSessionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case WaitingWaiterConfirmation = 'waiting_waiter_confirmation';
    case PaymentRequested = 'payment_requested';
    case Paid = 'paid';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::WaitingWaiterConfirmation => 'Waiting waiter confirmation',
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
