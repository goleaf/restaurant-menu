<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Gate;

final class UserNotificationQueryService
{
    public const PANEL_LIMIT = 20;

    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveBranches,
    ) {}

    /** @return Builder<DatabaseNotification> */
    public function accessibleQuery(User $user): Builder
    {
        return $user->notifications()->getQuery()
            ->select(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at'])
            ->whereIn('type', ['draft_order_sent_to_waiter', 'waiter_called', 'bill_requested', 'kitchen_item_ready'])
            ->whereIn('data->branch_id', $this->resolveBranches->handle($user, SystemPermission::ViewOrders));
    }

    /** @return array{count: int, notifications: DatabaseNotificationCollection<int, DatabaseNotification>, destinations: array<string, true>, cursor: ?Cursor, older_cursor: ?Cursor, newer_cursor: ?Cursor} */
    public function snapshot(User $user, bool $includeDetails, ?Cursor $cursor = null): array
    {
        $query = $this->accessibleQuery($user);

        $count = (clone $query)->whereNull('read_at')->count();
        $page = null;

        if ($includeDetails) {
            $query->reorder()->orderByDesc('created_at')->orderByDesc('id');
            // An empty cursor prevents URL parameters from controlling the private panel.
            $page = (clone $query)->cursorPaginate(self::PANEL_LIMIT, cursor: $cursor ?? '');

            if ($page->isEmpty() && $cursor !== null) {
                $page = $query->cursorPaginate(self::PANEL_LIMIT, cursor: '');
            }
        }

        $notifications = new DatabaseNotificationCollection($page?->items() ?? []);
        $contexts = $notifications->map(fn (DatabaseNotification $notification): ?array => $this->sessionContext($notification))->filter();
        $sessions = $contexts->isEmpty() ? collect() : TableSession::query()->select(['id', 'branch_id', 'service_point_id'])
            ->whereIn('id', $contexts->pluck('id'))->whereIn('branch_id', $contexts->pluck('branch_id'))->get()->keyBy('id');
        $destinations = [];

        foreach ($contexts as $index => $context) {
            $session = $sessions->get($context['id']);
            if ($session instanceof TableSession && (int) $session->branch_id === $context['branch_id']
                && (int) $session->service_point_id === $context['service_point_id']) {
                $destinations[$notifications[$index]->id] = true;
            }
        }

        return [
            'count' => $count,
            'notifications' => $notifications,
            'destinations' => $destinations,
            'cursor' => $page?->onFirstPage() ? null : $page?->cursor(),
            'older_cursor' => $page?->nextCursor(),
            'newer_cursor' => $page?->previousCursor(),
        ];
    }

    public function destination(User $user, string $notificationId): ?string
    {
        $notification = $this->accessibleQuery($user)->whereKey($notificationId)->first();

        if (! $notification instanceof DatabaseNotification) {
            return null;
        }

        $context = $this->sessionContext($notification);

        if ($context === null) {
            return null;
        }

        $session = TableSession::query()->select(['id', 'branch_id', 'service_point_id'])
            ->whereKey($context['id'])->where('branch_id', $context['branch_id'])->where('service_point_id', $context['service_point_id'])->first();

        if (! $session instanceof TableSession || Gate::forUser($user)->denies('view', $session)) {
            return null;
        }

        return route('restaurant.waiter.tables.show', $session);
    }

    /** @return array{id: int, branch_id: int, service_point_id: int}|null */
    private function sessionContext(DatabaseNotification $notification): ?array
    {
        $sessionId = data_get($notification->data, 'table_session_id');
        $branchId = data_get($notification->data, 'branch_id');
        $pointId = data_get($notification->data, 'service_point_id');

        return is_int($sessionId) && is_int($branchId) && is_int($pointId)
            ? ['id' => $sessionId, 'branch_id' => $branchId, 'service_point_id' => $pointId]
            : null;
    }
}
