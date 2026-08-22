<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\KitchenTicketItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KitchenItemReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly KitchenTicketItem $ticketItem,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'kitchen_item_ready';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $ticketItem = $this->ticketItem->loadMissing([
            'kitchenTicket:id,order_id,branch_id,service_point_id,table_session_id,kitchen_department_id,department_type,department_name',
            'kitchenTicket.branch:id,name',
            'kitchenTicket.servicePoint:id,branch_id,area_node_id,name,display_number',
            'kitchenTicket.servicePoint.areaNode:id,branch_id,name',
        ]);
        $kitchenTicket = $ticketItem->kitchenTicket;

        return [
            'kitchen_ticket_item_id' => $ticketItem->id,
            'kitchen_ticket_id' => $ticketItem->kitchen_ticket_id,
            'order_id' => $kitchenTicket->order_id,
            'table_session_id' => $kitchenTicket->table_session_id,
            'branch_id' => $kitchenTicket->branch_id,
            'branch_name' => $kitchenTicket->branch->name,
            'service_point_id' => $kitchenTicket->service_point_id,
            'service_point_name' => $kitchenTicket->servicePoint->name,
            'service_point_display_number' => $kitchenTicket->servicePoint->display_number,
            'area_name' => $kitchenTicket->servicePoint->areaNode?->name,
            'department_type' => $kitchenTicket->department_type,
            'department_name' => $kitchenTicket->department_name,
            'item_name' => $ticketItem->item_name,
            'guest_name' => $ticketItem->guest_name,
            'quantity' => $ticketItem->quantity,
            'ready_at' => $ticketItem->updated_at?->toISOString(),
            'message' => __('ui.notifications.kitchenitemreadynotification.kuxnia_ili_bar_otmetili_pozic'),
        ];
    }
}
