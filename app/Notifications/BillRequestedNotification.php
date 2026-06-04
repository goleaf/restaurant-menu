<?php

namespace App\Notifications;

use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BillRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TableSession $tableSession,
        public readonly TableSessionGuest $guest,
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
        return 'bill_requested';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $tableSession = $this->tableSession->loadMissing([
            'branch:id,name',
            'servicePoint:id,branch_id,area_node_id,name,display_number',
            'servicePoint.areaNode:id,branch_id,name',
        ]);

        return [
            'table_session_id' => $tableSession->id,
            'branch_id' => $tableSession->branch_id,
            'branch_name' => $tableSession->branch?->name,
            'service_point_id' => $tableSession->service_point_id,
            'service_point_name' => $tableSession->servicePoint?->name,
            'service_point_display_number' => $tableSession->servicePoint?->display_number,
            'area_name' => $tableSession->servicePoint?->areaNode?->name,
            'guest_id' => $this->guest->id,
            'guest_name' => $this->guest->guest_name,
            'requested_at' => data_get($tableSession->metadata, 'bill_requested_at'),
            'message' => __('Гость попросил счёт.'),
        ];
    }
}
