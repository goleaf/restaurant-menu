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
     * @return list<string>
     */
    public static function guestViewableValues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status): bool => $status->allowsGuestViewing())
            ->map(fn (self $status): string => $status->value)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status): bool => $status->occupiesServicePoint())
            ->map(fn (self $status): string => $status->value)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function guestEntryBlockedValues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status): bool => $status->blocksNewGuestEntry())
            ->map(fn (self $status): string => $status->value)
            ->values()
            ->all();
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
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
