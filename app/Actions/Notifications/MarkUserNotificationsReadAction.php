<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

final class MarkUserNotificationsReadAction
{
    public function one(User $user, string $notificationId): bool
    {
        $notification = $user->unreadNotifications()->whereKey($notificationId)->first();

        if (! $notification instanceof DatabaseNotification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function all(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
