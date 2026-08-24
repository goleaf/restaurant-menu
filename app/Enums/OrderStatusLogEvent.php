<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatusLogEvent: string
{
    case DraftCreated = 'draft_created';
    case DraftEdited = 'draft_edited';
    case DraftSentToWaiter = 'draft_sent_to_waiter';
    case DraftConfirmed = 'draft_confirmed';
    case DraftRejected = 'draft_rejected';
    case DraftReturnedToDraft = 'draft_returned_to_draft';
    case OrderStatusChanged = 'order_status_changed';
    case OrderSentToKitchenBar = 'order_sent_to_kitchen_bar';
    case OrderCancelled = 'order_cancelled';
    case OrderItemCancelled = 'order_item_cancelled';
    case TicketItemStatusChanged = 'ticket_item_status_changed';
    case TicketItemServed = 'ticket_item_served';

    public function label(): string
    {
        return __(match ($this) {
            self::DraftCreated => 'statuses.order_event.draft_created',
            self::DraftEdited => 'statuses.order_event.draft_edited',
            self::DraftSentToWaiter => 'statuses.order_event.draft_sent_to_waiter',
            self::DraftConfirmed => 'statuses.order_event.draft_confirmed',
            self::DraftRejected => 'statuses.order_event.draft_rejected',
            self::DraftReturnedToDraft => 'statuses.order_event.draft_returned_to_draft',
            self::OrderStatusChanged => 'statuses.order_event.order_status_changed',
            self::OrderSentToKitchenBar => 'statuses.order_event.order_sent_to_kitchen_bar',
            self::OrderCancelled => 'statuses.order_event.order_cancelled',
            self::OrderItemCancelled => 'statuses.order_event.order_item_cancelled',
            self::TicketItemStatusChanged => 'statuses.order_event.ticket_item_status_changed',
            self::TicketItemServed => 'statuses.order_event.ticket_item_served',
        });
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $event): string => $event->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $event): array => [$event->value => $event->label()])
            ->all();
    }
}
