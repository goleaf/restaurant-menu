<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionTableOrdersAction
{
    public function __construct(
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    public function handle(
        TableSession $tableSession,
        OrderStatus $targetStatus,
        ?User $actorUser = null,
        ?TableSessionGuest $actorGuest = null,
        string $errorField = 'order_status',
    ): int {
        return DB::transaction(function () use ($tableSession, $targetStatus, $actorUser, $actorGuest, $errorField): int {
            $orders = Order::query()
                ->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'table_session_id',
                    'draft_order_id',
                    'status',
                    'metadata',
                ])
                ->where('table_session_id', $tableSession->id)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->orderBy('id')
                ->limit(500)
                ->lockForUpdate()
                ->get();

            $transitionedCount = 0;

            foreach ($orders as $order) {
                if ($order->status === $targetStatus || $order->status === OrderStatus::Closed) {
                    continue;
                }

                if (! $order->status->canTransitionTo($targetStatus)) {
                    if ($targetStatus === OrderStatus::PaymentRequested) {
                        continue;
                    }

                    throw ValidationException::withMessages([
                        $errorField => __('errors.types.order_invalid_transition.message'),
                    ]);
                }

                $previousStatus = $order->status;
                $metadata = $order->metadata ?? [];

                $order->forceFill([
                    'status' => $targetStatus,
                    'metadata' => array_merge($metadata, [
                        'table_session_status_synced_at' => now()->toISOString(),
                        'table_session_status_synced_by_user_id' => $actorUser?->id,
                        'table_session_status_synced_by_guest_id' => $actorGuest?->id,
                    ]),
                ])->save();

                $this->createOrderStatusLog->handle(
                    event: OrderStatusLogEvent::OrderStatusChanged,
                    order: $order,
                    actorUser: $actorUser,
                    actorGuest: $actorGuest,
                    previousStatus: $previousStatus,
                    newStatus: $targetStatus,
                    statusType: 'order',
                    metadata: [
                        'source' => 'table_session_lifecycle',
                        'table_session_status' => $tableSession->status->value,
                    ],
                );

                $transitionedCount++;
            }

            return $transitionedCount;
        }, attempts: 3);
    }
}
