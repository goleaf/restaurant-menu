<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WaiterCall;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WaiterCalledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly WaiterCall $waiterCall,
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
        return 'waiter_called';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $waiterCall = $this->waiterCall->loadMissing([
            'branch:id,name',
            'servicePoint:id,branch_id,area_node_id,name,display_number',
            'servicePoint.areaNode:id,branch_id,name',
            'requestedByGuest:id,guest_name',
        ]);

        return [
            'waiter_call_id' => $waiterCall->id,
            'branch_id' => $waiterCall->branch_id,
            'branch_name' => $waiterCall->branch->name,
            'service_point_id' => $waiterCall->service_point_id,
            'service_point_name' => $waiterCall->servicePoint->name,
            'service_point_display_number' => $waiterCall->servicePoint->display_number,
            'area_name' => $waiterCall->servicePoint->areaNode?->name,
            'table_session_id' => $waiterCall->table_session_id,
            'guest_name' => $waiterCall->requested_by_guest_id === null ? null : $waiterCall->requestedByGuest->guest_name,
            'requested_at' => $waiterCall->requested_at->toISOString(),
            'message' => __('ui.livewire.notifications.unreadcount.gost_zovet_oficianta'),
        ];
    }
}
