<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use App\Services\Notifications\UserNotificationQueryService;

final class MarkUserNotificationsReadAction
{
    public function __construct(
        private readonly UserNotificationQueryService $notificationQueries,
    ) {}

    public function one(User $user, string $notificationId): bool
    {
        return $this->notificationQueries->accessibleQuery($user)
            ->whereKey($notificationId)->whereNull('read_at')->update(['read_at' => now()]) === 1;
    }

    public function all(User $user): int
    {
        return $this->notificationQueries->accessibleQuery($user)
            ->whereNull('read_at')->update(['read_at' => now()]);
    }
}
