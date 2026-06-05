<?php

namespace App\Notifications;

use App\Models\TableSessionJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JoinRequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TableSessionJoinRequest $joinRequest,
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
        return 'join_request_created';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $joinRequest = $this->joinRequest->loadMissing([
            'tableSession:id,branch_id,service_point_id',
            'tableSession.branch:id,name',
            'tableSession.servicePoint:id,branch_id,area_node_id,name,display_number',
            'tableSession.servicePoint.areaNode:id,branch_id,name',
        ]);
        $tableSession = $joinRequest->tableSession;

        return [
            'join_request_id' => $joinRequest->id,
            'table_session_id' => $joinRequest->table_session_id,
            'branch_id' => $tableSession?->branch_id,
            'branch_name' => $tableSession?->branch?->name,
            'service_point_id' => $tableSession?->service_point_id,
            'service_point_name' => $tableSession?->servicePoint?->name,
            'service_point_display_number' => $tableSession?->servicePoint?->display_number,
            'area_name' => $tableSession?->servicePoint?->areaNode?->name,
            'guest_name' => $joinRequest->guest_name,
            'expires_at' => $joinRequest->expires_at?->toISOString(),
            'message' => __('ui.livewire.publicqr.notifications.novyi_gost_zdet_podtverzdeniia'),
        ];
    }
}
