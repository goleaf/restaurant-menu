<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatusLogEvent;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;

class CreateOrderStatusLogAction
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        OrderStatusLogEvent $event,
        ?Order $order = null,
        ?DraftOrder $draftOrder = null,
        ?User $actorUser = null,
        ?TableSessionGuest $actorGuest = null,
        BackedEnum|string|null $previousStatus = null,
        BackedEnum|string|null $newStatus = null,
        ?string $statusType = null,
        ?string $reason = null,
        array $metadata = [],
        ?DateTimeInterface $occurredAt = null,
    ): OrderStatusLog {
        $context = $this->resolveContext($order, $draftOrder);
        $actor = $this->resolveActor($actorUser, $actorGuest);

        return OrderStatusLog::query()->create([
            'branch_id' => $context['branch_id'],
            'service_point_id' => $context['service_point_id'],
            'table_session_id' => $context['table_session_id'],
            'draft_order_id' => $draftOrder?->id ?? $order?->draft_order_id,
            'order_id' => $order?->id,
            'actor_user_id' => $actorUser?->id,
            'actor_guest_id' => $actorGuest?->id,
            'actor_type' => $actor['type'],
            'actor_name' => $actor['name'],
            'event' => $event,
            'status_type' => $statusType ?? $this->resolveStatusType($order, $draftOrder),
            'previous_status' => $this->statusValue($previousStatus),
            'new_status' => $this->statusValue($newStatus),
            'reason' => $reason,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * @return array{branch_id: int|null, service_point_id: int|null, table_session_id: int|null}
     */
    private function resolveContext(?Order $order, ?DraftOrder $draftOrder): array
    {
        $tableSessionId = $order?->table_session_id ?? $draftOrder?->table_session_id;
        $tableSession = $tableSessionId === null
            ? null
            : TableSession::query()
                ->select(['id', 'branch_id', 'service_point_id'])
                ->whereKey($tableSessionId)
                ->first();

        return [
            'branch_id' => $order?->branch_id ?? $tableSession?->branch_id,
            'service_point_id' => $order?->service_point_id ?? $tableSession?->service_point_id,
            'table_session_id' => $tableSessionId,
        ];
    }

    /**
     * @return array{type: string|null, name: string|null}
     */
    private function resolveActor(?User $actorUser, ?TableSessionGuest $actorGuest): array
    {
        if ($actorUser instanceof User) {
            return [
                'type' => 'user',
                'name' => $actorUser->name,
            ];
        }

        if ($actorGuest instanceof TableSessionGuest) {
            return [
                'type' => 'guest',
                'name' => $actorGuest->guest_name,
            ];
        }

        return [
            'type' => null,
            'name' => null,
        ];
    }

    private function statusValue(BackedEnum|string|null $status): ?string
    {
        if ($status instanceof BackedEnum) {
            return (string) $status->value;
        }

        return $status;
    }

    private function resolveStatusType(?Order $order, ?DraftOrder $draftOrder): ?string
    {
        if ($order instanceof Order) {
            return 'order';
        }

        if ($draftOrder instanceof DraftOrder) {
            return 'draft_order';
        }

        return null;
    }
}
