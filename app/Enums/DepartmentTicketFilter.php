<?php

declare(strict_types=1);

namespace App\Enums;

enum DepartmentTicketFilter: string
{
    case Active = 'active';
    case New = 'new';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case History = 'history';

    public function label(): string
    {
        return __(match ($this) {
            self::Active => 'ui.departments.dashboard.filters.active',
            self::New => 'statuses.kitchen_ticket_item.new',
            self::Accepted => 'statuses.kitchen_ticket_item.accepted',
            self::InProgress => 'statuses.kitchen_ticket_item.in_progress',
            self::Ready => 'statuses.kitchen_ticket_item.ready',
            self::Completed => 'ui.departments.dashboard.completed',
            self::Cancelled => 'statuses.kitchen_ticket_item.cancelled',
            self::History => 'preparation.views.history',
        });
    }

    public function isHistory(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::History], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $filter): array => [$filter->value => $filter->label()])
            ->all();
    }
}
