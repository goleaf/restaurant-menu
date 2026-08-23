<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;

final class UserNotificationQueryService
{
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /** @return DatabaseNotificationCollection<int, DatabaseNotification> */
    public function recentUnread(User $user, int $limit = 8): DatabaseNotificationCollection
    {
        return $user->unreadNotifications()
            ->select(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
