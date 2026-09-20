<?php

declare(strict_types=1);

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

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }

    public function allowsGuestParticipation(): bool
    {
        return in_array($this, [self::Pending, self::Active], true);
    }

    public function allowsGuestViewing(): bool
    {
        return ! $this->isTerminal();
    }

    public function occupiesServicePoint(): bool
    {
        return in_array($this, [
            self::Active,
            self::WaitingWaiterConfirmation,
            self::PaymentRequested,
        ], true);
    }

    public function blocksNewGuestEntry(): bool
    {
        return $this->allowsGuestViewing() && ! $this->allowsGuestParticipation();
    }

    public function allowsPaymentRecording(): bool
    {
        return in_array($this, [self::Active, self::PaymentRequested], true);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return in_array($next, match ($this) {
            self::Pending => [self::Active, self::WaitingWaiterConfirmation, self::Closed, self::Cancelled],
            self::Active => [self::WaitingWaiterConfirmation, self::PaymentRequested, self::Paid, self::Closed, self::Cancelled],
            self::WaitingWaiterConfirmation => [self::Active, self::Closed, self::Cancelled],
            self::PaymentRequested => [self::Paid, self::Closed, self::Cancelled],
            self::Paid => [self::Closed],
            self::Closed, self::Cancelled => [],
        }, true);
    }

    public function locksOrderChanges(): bool
    {
        return in_array($this, [self::PaymentRequested, self::Paid], true)
            || $this->isTerminal();
    }

    public function label(): string
    {
        return __(match ($this) {
            self::Pending => 'statuses.table_session.pending',
            self::Active => 'statuses.table_session.active',
            self::WaitingWaiterConfirmation => 'statuses.table_session.waiting_waiter_confirmation',
            self::PaymentRequested => 'statuses.table_session.payment_requested',
            self::Paid => 'statuses.table_session.paid',
            self::Closed => 'statuses.table_session.closed',
            self::Cancelled => 'statuses.table_session.cancelled',
        });
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function guestViewableValues(): array
    {
        return array_column(array_filter(
            self::cases(),
            fn (self $status): bool => $status->allowsGuestViewing(),
        ), 'value');
    }

    /**
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return array_column(array_filter(
            self::cases(),
            fn (self $status): bool => $status->occupiesServicePoint(),
        ), 'value');
    }

    /**
     * @return list<string>
     */
    public static function guestEntryBlockedValues(): array
    {
        return array_column(array_filter(
            self::cases(),
            fn (self $status): bool => $status->blocksNewGuestEntry(),
        ), 'value');
    }

    /**
     * @return list<string>
     */
    public static function reusableOpenValues(): array
    {
        return [
            self::Active->value,
            self::WaitingWaiterConfirmation->value,
            self::PaymentRequested->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
