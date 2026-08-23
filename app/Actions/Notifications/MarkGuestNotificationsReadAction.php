<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\TableSessionGuest;
use Illuminate\Notifications\DatabaseNotification;

final class MarkGuestNotificationsReadAction
{
    /** @param list<class-string> $types */
    public function one(TableSessionGuest $guest, string $notificationId, array $types): bool
    {
        $notification = $guest->unreadNotifications()
            ->whereIn('type', $types)
            ->whereKey($notificationId)
            ->first();

        if (! $notification instanceof DatabaseNotification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    /** @param list<class-string> $types */
    public function all(TableSessionGuest $guest, array $types): int
    {
        return $guest->unreadNotifications()
            ->whereIn('type', $types)
            ->update(['read_at' => now()]);
    }
}
