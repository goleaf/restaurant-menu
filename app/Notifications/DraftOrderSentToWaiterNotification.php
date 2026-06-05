<?php

namespace App\Notifications;

use App\Models\DraftOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DraftOrderSentToWaiterNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly DraftOrder $draftOrder,
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
        return 'draft_order_sent_to_waiter';
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
            'sentByGuest:id,guest_name',
        ]);
        $tableSession = $draftOrder->tableSession;

        return [
            'draft_order_id' => $draftOrder->id,
            'table_session_id' => $draftOrder->table_session_id,
            'branch_id' => $tableSession?->branch_id,
            'branch_name' => $tableSession?->branch?->name,
            'service_point_id' => $tableSession?->service_point_id,
            'service_point_name' => $tableSession?->servicePoint?->name,
            'service_point_display_number' => $tableSession?->servicePoint?->display_number,
            'area_name' => $tableSession?->servicePoint?->areaNode?->name,
            'sent_by_guest_id' => $draftOrder->sent_by_guest_id,
            'sent_by_guest_name' => $draftOrder->sentByGuest?->guest_name,
            'items_count' => (int) ($draftOrder->items_count ?? $draftOrder->items()->count()),
            'sent_at' => $draftOrder->sent_to_waiter_at?->toISOString(),
            'message' => __('ui.livewire.notifications.unreadcount.novyi_zakaz_otpravlen_oficiantu'),
        ];
    }
}
