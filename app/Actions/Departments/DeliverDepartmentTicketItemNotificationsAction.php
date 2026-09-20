<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\Actions\Waiter\ResolveWaiterNotificationRecipientsAction;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\TableSessionGuestStatus;
use App\Models\KitchenTicketItem;
use App\Models\OrderStatusLog;
use App\Models\TableSessionGuest;
use App\Notifications\KitchenItemCookingNotification;
use App\Notifications\KitchenItemReadyNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

final class DeliverDepartmentTicketItemNotificationsAction
{
    public function __construct(
        private readonly ResolveWaiterNotificationRecipientsAction $resolveWaiterRecipients,
    ) {}

    public function handle(int $eventId): void
    {
        DB::transaction(function () use ($eventId): void {
            $event = OrderStatusLog::query()
                ->select(['id', 'branch_id', 'order_id', 'event', 'new_status', 'metadata', 'occurred_at'])
                ->whereKey($eventId)
                ->where('event', OrderStatusLogEvent::TicketItemStatusChanged->value)
                ->lockForUpdate()
                ->firstOrFail();

            if (data_get($event->metadata, 'notifications.state') !== 'pending') {
                return;
            }

            $item = KitchenTicketItem::query()
                ->select(['id', 'kitchen_ticket_id', 'order_item_id', 'table_session_guest_id', 'guest_name', 'item_name', 'quantity', 'status', 'served_at'])
                ->with([
                    'guest:id,table_session_id,status',
                    'kitchenTicket' => fn ($query) => $query
                        ->select(['id', 'order_id', 'branch_id', 'service_point_id', 'table_session_id', 'kitchen_department_id', 'department_type', 'department_name'])
                        ->with([
                            'order:id,status',
                            'branch:id,organization_id,name',
                            'servicePoint:id,branch_id,area_node_id,name,display_number',
                            'servicePoint.areaNode:id,branch_id,name',
                            'tableSession:id,branch_id,service_point_id,status',
                            'tableSession.servicePoint:id,branch_id,area_node_id,name,display_number',
                            'tableSession.servicePoint.areaNode:id,branch_id,name',
                        ]),
                ])
                ->whereKey(data_get($event->metadata, 'kitchen_ticket_item_id'))
                ->whereHas('kitchenTicket', fn ($query) => $query
                    ->where('order_id', $event->order_id)
                    ->where('branch_id', $event->branch_id))
                ->firstOrFail();

            $state = 'superseded';
            if ($item->status->value === $event->new_status && $item->served_at === null
                && $item->kitchenTicket->tableSession->branch_id === $item->kitchenTicket->branch_id
                && ! $item->kitchenTicket->tableSession->status->locksOrderChanges()
                && ! in_array($item->kitchenTicket->order->status, [OrderStatus::Served, OrderStatus::PaymentRequested, OrderStatus::Paid, OrderStatus::Closed, OrderStatus::Cancelled], true)) {
                $occurredAt = $event->occurred_at->toISOString();
                if ($item->status === KitchenTicketItemStatus::Ready) {
                    $recipients = $this->resolveWaiterRecipients->handle($item->kitchenTicket->branch);
                    Notification::send($recipients, new KitchenItemReadyNotification($item, $occurredAt, $event->id));
                }

                $guest = $item->guest;
                if ($guest instanceof TableSessionGuest && $guest->status === TableSessionGuestStatus::Active
                    && $guest->table_session_id === $item->kitchenTicket->table_session_id) {
                    $guest->notify($item->status === KitchenTicketItemStatus::Ready
                        ? new KitchenItemReadyNotification($item, $occurredAt, $event->id)
                        : new KitchenItemCookingNotification($item, $occurredAt, $event->id));
                }
                $state = 'delivered';
            }

            $metadata = $event->metadata ?? [];
            $metadata['notifications'] = ['state' => $state, 'resolved_at' => now()->toISOString()];
            if (! $event->forceFill(['metadata' => $metadata])->save()) {
                throw new RuntimeException('Preparation notification delivery was not recorded.');
            }
        }, attempts: 3);
    }
}
