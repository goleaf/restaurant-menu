<?php

namespace App\Notifications;

use App\Models\DraftOrder;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DraftOrderConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly DraftOrder $draftOrder,
        public readonly Order $order,
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
        return 'draft_order_confirmed';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $draftOrder = $this->draftOrder->loadMissing([
            'tableSession:id,branch_id,service_point_id',
            'tableSession.branch:id,name',
            'tableSession.servicePoint:id,branch_id,area_node_id,name,display_number',
            'tableSession.servicePoint.areaNode:id,branch_id,name',
            'convertedByUser:id,name',
        ]);
        $order = $this->order->loadMissing([
            'confirmedByUser:id,name',
        ]);
        $tableSession = $draftOrder->tableSession;

        return [
            'draft_order_id' => $draftOrder->id,
            'order_id' => $order->id,
            'table_session_id' => $draftOrder->table_session_id,
            'branch_id' => $tableSession?->branch_id,
            'branch_name' => $tableSession?->branch?->name,
            'service_point_id' => $tableSession?->service_point_id,
            'service_point_name' => $tableSession?->servicePoint?->name,
            'service_point_display_number' => $tableSession?->servicePoint?->display_number,
            'area_name' => $tableSession?->servicePoint?->areaNode?->name,
            'confirmed_by_user_id' => $order->confirmed_by_user_id,
            'confirmed_by_user_name' => $order->confirmedByUser?->name ?? $draftOrder->convertedByUser?->name,
            'confirmed_at' => $order->confirmed_at?->toISOString(),
            'total_price' => $order->total_price,
            'currency' => $order->currency,
            'message' => __('ui.livewire.publicqr.notifications.oficiant_podtverdil_zakaz'),
        ];
    }
}
