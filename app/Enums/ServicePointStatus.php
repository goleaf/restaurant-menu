<?php

namespace App\Enums;

enum ServicePointStatus: string
{
    case Free = 'free';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case WaitingWaiter = 'waiting_waiter';
    case HasNewOrder = 'has_new_order';
    case Cooking = 'cooking';
    case ReadyToServe = 'ready_to_serve';
    case PaymentRequested = 'payment_requested';
    case Paid = 'paid';
    case Closed = 'closed';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Occupied => 'Occupied',
            self::Reserved => 'Reserved',
            self::WaitingWaiter => 'Waiting waiter',
            self::HasNewOrder => 'Has new order',
            self::Cooking => 'Cooking',
            self::ReadyToServe => 'Ready to serve',
            self::PaymentRequested => 'Payment requested',
            self::Paid => 'Paid',
            self::Closed => 'Closed',
            self::Blocked => 'Blocked',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Free => 'green',
            self::Occupied => 'sky',
            self::Reserved => 'amber',
            self::WaitingWaiter => 'orange',
            self::HasNewOrder => 'rose',
            self::Cooking => 'violet',
            self::ReadyToServe => 'emerald',
            self::PaymentRequested => 'blue',
            self::Paid => 'lime',
            self::Closed => 'zinc',
            self::Blocked => 'red',
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
