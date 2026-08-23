<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\BusinessRuleCode;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Exceptions\BusinessRuleViolation;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\User;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeOrderStatusAction
{
    public function __construct(
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(Order $order, OrderStatus $newStatus, User $changedBy, ?string $reason = null, array $metadata = []): Order
    {
        return DB::transaction(function () use ($order, $newStatus, $changedBy, $reason, $metadata): Order {
            $order = $this->reloadOrder($order);
            $previousStatus = $order->status;

            if ($previousStatus === OrderStatus::Cancelled) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::OrderAlreadyCancelled,
                    'order_status',
                    __('ui.actions.orders.changeorderstatusaction.zakaz_uze_otmenen'),
                );
            }

            $this->ensureCanChangeStatus($order, $newStatus, $changedBy);

            $normalizedReason = $this->normalizeReason($reason);

            if ($previousStatus === $newStatus) {
                return $order;
            }

            if ($newStatus === OrderStatus::Cancelled && $normalizedReason === null) {
                throw ValidationException::withMessages([
                    'orderCancellationReason' => __('ui.actions.orders.changeorderstatusaction.ukazite_pricinu_otmeny_zakaza'),
                ]);
            }

            $ticketItemWarningCounts = $newStatus === OrderStatus::Cancelled
                ? $this->readyAndServedTicketItemCounts($order)
                : ['ready' => 0, 'served' => 0];
            $orderMetadata = $this->orderMetadataForStatusChange(
                order: $order,
                newStatus: $newStatus,
                changedBy: $changedBy,
                reason: $normalizedReason,
                ticketItemWarningCounts: $ticketItemWarningCounts,
            );
            $logMetadata = ['source' => 'manual_status_change'] + $metadata;

            if ($newStatus === OrderStatus::Cancelled) {
                $logMetadata += [
                    'ready_ticket_items_count' => $ticketItemWarningCounts['ready'],
                    'served_ticket_items_count' => $ticketItemWarningCounts['served'],
                ];
            }

            $order
                ->forceFill([
                    'status' => $newStatus,
                    'metadata' => $orderMetadata,
                ])
                ->save();

            $this->createOrderStatusLog->handle(
                event: $this->eventFor($newStatus),
                order: $order,
                actorUser: $changedBy,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                statusType: 'order',
                reason: $normalizedReason,
                metadata: $logMetadata,
            );

            if ($newStatus === OrderStatus::Cancelled) {
                $this->recordAuditLog->handle(
                    action: AuditLogAction::OrderCancelled,
                    entityType: 'order',
                    entityId: $order->id,
                    actorUser: $changedBy,
                    organizationId: $order->branch?->organization_id,
                    branchId: $order->branch_id,
                    oldValues: [
                        'status' => $previousStatus,
                    ],
                    newValues: [
                        'status' => $newStatus,
                        'reason' => $normalizedReason,
                        'ready_ticket_items_count' => $ticketItemWarningCounts['ready'],
                        'served_ticket_items_count' => $ticketItemWarningCounts['served'],
                    ],
                );
            }

            return $order->refresh();
        });
    }

    private function reloadOrder(Order $order): Order
    {
        return Order::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'table_session_id',
                'draft_order_id',
                'status',
                'metadata',
            ])
            ->with(['branch:id,organization_id'])
            ->whereKey($order->id)
            ->firstOrFail();
    }

    private function ensureCanChangeStatus(Order $order, OrderStatus $newStatus, User $user): void
    {
        if (Gate::forUser($user)->denies($this->abilityFor($newStatus), $order)) {
            throw ValidationException::withMessages([
                'order_status' => __('ui.actions.orders.changeorderstatusaction.u_vas_net_prava_meniat_etot_statu'),
            ]);
        }
    }

    private function abilityFor(OrderStatus $newStatus): string
    {
        return match ($newStatus) {
            OrderStatus::SentToKitchenBar => 'sendToKitchen',
            OrderStatus::Cancelled => 'cancel',
            default => 'confirm',
        };
    }

    private function eventFor(OrderStatus $newStatus): OrderStatusLogEvent
    {
        return match ($newStatus) {
            OrderStatus::SentToKitchenBar => OrderStatusLogEvent::OrderSentToKitchenBar,
            OrderStatus::Cancelled => OrderStatusLogEvent::OrderCancelled,
            default => OrderStatusLogEvent::OrderStatusChanged,
        };
    }

    private function normalizeReason(?string $reason): ?string
    {
        return PlainText::optional($reason, 500);
    }

    /**
     * @return array{ready: int, served: int}
     */
    private function readyAndServedTicketItemCounts(Order $order): array
    {
        $ticketItems = KitchenTicketItem::query()
            ->select(['id', 'kitchen_ticket_id', 'status', 'served_at'])
            ->whereHas('kitchenTicket', function ($query) use ($order): void {
                $query->where('order_id', $order->id);
            })
            ->limit(500)
            ->get();

        return [
            'ready' => $ticketItems
                ->filter(fn (KitchenTicketItem $item): bool => $item->status === KitchenTicketItemStatus::Ready)
                ->count(),
            'served' => $ticketItems
                ->filter(fn (KitchenTicketItem $item): bool => $item->served_at !== null)
                ->count(),
        ];
    }

    /**
     * @param  array{ready: int, served: int}  $ticketItemWarningCounts
     * @return array<string, mixed>
     */
    private function orderMetadataForStatusChange(
        Order $order,
        OrderStatus $newStatus,
        User $changedBy,
        ?string $reason,
        array $ticketItemWarningCounts,
    ): array {
        $metadata = $order->metadata ?? [];

        if ($newStatus !== OrderStatus::Cancelled) {
            return $metadata;
        }

        return array_merge($metadata, [
            'cancelled_at' => now()->toISOString(),
            'cancelled_by_user_id' => $changedBy->id,
            'cancellation_reason' => $reason,
            'ready_ticket_items_at_cancellation' => $ticketItemWarningCounts['ready'],
            'served_ticket_items_at_cancellation' => $ticketItemWarningCounts['served'],
        ]);
    }
}
