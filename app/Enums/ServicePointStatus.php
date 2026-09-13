<?php

declare(strict_types=1);

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

    public function allowsTableOpening(): bool
    {
        return in_array($this, [self::Free, self::Reserved], true);
    }

    public function label(): string
    {
        return __(match ($this) {
            self::Free => 'reports.statuses.service_points.free',
            self::Occupied => 'reports.statuses.service_points.occupied',
            self::Reserved => 'reports.statuses.service_points.reserved',
            self::WaitingWaiter => 'reports.statuses.service_points.waiting_waiter',
            self::HasNewOrder => 'reports.statuses.service_points.has_new_order',
            self::Cooking => 'reports.statuses.service_points.cooking',
            self::ReadyToServe => 'reports.statuses.service_points.ready_to_serve',
            self::PaymentRequested => 'reports.statuses.service_points.payment_requested',
            self::Paid => 'reports.statuses.service_points.paid',
            self::Closed => 'reports.statuses.service_points.closed',
            self::Blocked => 'reports.statuses.service_points.blocked',
        });
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
