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

    public function label(): string
    {
        return match ($this) {
            self::DraftCreated => 'Draft created',
            self::DraftEdited => 'Draft edited',
            self::DraftSentToWaiter => 'Draft sent to waiter',
            self::DraftConfirmed => 'Draft confirmed',
            self::DraftRejected => 'Draft rejected',
            self::DraftReturnedToDraft => 'Draft returned to draft',
            self::OrderStatusChanged => 'Order status changed',
            self::OrderSentToKitchenBar => 'Order sent to kitchen/bar',
            self::OrderCancelled => 'Order cancelled',
            self::OrderItemCancelled => 'Order item cancelled',
        };
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
