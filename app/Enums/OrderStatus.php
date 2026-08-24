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

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return in_array($next, match ($this) {
            self::ConfirmedByWaiter => [self::SentToKitchenBar, self::Cancelled],
            self::SentToKitchenBar => [self::InProgress, self::Ready, self::Cancelled],
            self::InProgress => [self::Ready, self::Cancelled],
            self::Ready => [self::Served, self::Cancelled],
            self::Served => [self::PaymentRequested, self::Paid, self::Closed, self::Cancelled],
            self::PaymentRequested => [self::Paid, self::Closed, self::Cancelled],
            self::Paid => [self::Closed],
            self::Closed, self::Cancelled => [],
        }, true);
    }

    public function allowsTableClosure(): bool
    {
        return in_array($this, [
            self::Served,
            self::PaymentRequested,
            self::Paid,
            self::Closed,
            self::Cancelled,
        ], true);
    }

    public function label(): string
    {
        return __(match ($this) {
            self::ConfirmedByWaiter => 'reports.statuses.orders.confirmed_by_waiter',
            self::SentToKitchenBar => 'reports.statuses.orders.sent_to_kitchen_bar',
            self::InProgress => 'reports.statuses.orders.in_progress',
            self::Ready => 'reports.statuses.orders.ready',
            self::Served => 'reports.statuses.orders.served',
            self::PaymentRequested => 'reports.statuses.orders.payment_requested',
            self::Paid => 'reports.statuses.orders.paid',
            self::Closed => 'reports.statuses.orders.closed',
            self::Cancelled => 'reports.statuses.orders.cancelled',
        });
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
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [
            self::ConfirmedByWaiter->value,
            self::SentToKitchenBar->value,
            self::InProgress->value,
            self::Ready->value,
            self::Served->value,
            self::PaymentRequested->value,
        ];
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
