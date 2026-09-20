<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Actions\Orders\SyncOrderStatusFromTicketItemsAction;
use App\Enums\AuditLogAction;
use App\Enums\BusinessRuleCode;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleViolation;
use App\Models\KitchenTicketItem;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UpdateDepartmentTicketItemStatusAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
        private readonly SyncOrderStatusFromTicketItemsAction $syncOrderStatus,
        private readonly DeliverDepartmentTicketItemNotificationsAction $deliverNotifications,
        private readonly ResolvePreparationAccessibleDepartmentIdsAction $resolvePreparationDepartments,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    public function handlePreparation(
        int $itemId,
        KitchenTicketItemStatus $status,
        User $user,
        int $branchId,
        KitchenTicketItemStatus $expectedStatus,
        string $expectedUpdatedAt,
        int $expectedTicketId,
    ): KitchenTicketItem {
        return $this->update(
            $itemId, $status, $user, [], [], [], $branchId,
            $expectedStatus, $expectedUpdatedAt, $expectedTicketId, true,
        );
    }

    /**
     * @param  list<KitchenDepartmentType>  $departmentTypes
     * @param  list<SystemRole>  $roleCodes
     * @param  list<SystemPermission>  $permissionCodes
     */
    public function handle(
        int $itemId,
        KitchenTicketItemStatus $status,
        User $user,
        array $departmentTypes,
        array $roleCodes,
        array $permissionCodes,
        ?int $branchId = null,
    ): KitchenTicketItem {
        return $this->update($itemId, $status, $user, $departmentTypes, $roleCodes, $permissionCodes, $branchId);
    }

    /**
     * @param  list<KitchenDepartmentType>  $departmentTypes
     * @param  list<SystemRole>  $roleCodes
     * @param  list<SystemPermission>  $permissionCodes
     */
    private function update(
        int $itemId,
        KitchenTicketItemStatus $status,
        User $user,
        array $departmentTypes,
        array $roleCodes,
        array $permissionCodes,
        ?int $branchId,
        ?KitchenTicketItemStatus $expectedStatus = null,
        ?string $expectedUpdatedAt = null,
        ?int $expectedTicketId = null,
        bool $preparationWorkspace = false,
    ): KitchenTicketItem {
        $transitionEvent = null;

        $item = DB::transaction(function () use (
            $itemId,
            $status,
            $user,
            $departmentTypes,
            $roleCodes,
            $permissionCodes,
            $branchId,
            $expectedStatus,
            $expectedUpdatedAt,
            $expectedTicketId,
            $preparationWorkspace,
            &$transitionEvent,
        ): KitchenTicketItem {
            $transitionEvent = null;
            $item = KitchenTicketItem::query()
                ->select(['id', 'kitchen_ticket_id', 'order_item_id', 'item_name', 'quantity', 'status', 'served_at', 'updated_at'])
                ->with([
                    'kitchenTicket' => fn ($query) => $query
                        ->select(['id', 'branch_id', 'kitchen_department_id', 'department_name', 'order_id', 'table_session_id'])
                        ->with([
                            'branch:id,organization_id',
                            'tableSession:id,branch_id,status',
                            'order' => fn ($orderQuery) => $orderQuery->select([
                                'id',
                                'branch_id',
                                'service_point_id',
                                'table_session_id',
                                'draft_order_id',
                                'status',
                                'metadata',
                            ]),
                        ]),
                ])
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($branchId !== null && $item->kitchenTicket->branch_id !== $branchId, 403);
            $departmentId = $item->kitchenTicket->kitchen_department_id;
            $accessibleDepartmentIds = $preparationWorkspace
                ? $this->resolvePreparationDepartments->handle($user, $branchId)
                : $this->resolveAccessibleDepartmentIds->handle(
                    user: $user,
                    departmentTypes: $departmentTypes,
                    roleCodes: $roleCodes,
                    permissionCodes: $permissionCodes,
                );

            if ($departmentId === null || ! $accessibleDepartmentIds->contains((int) $departmentId)) {
                throw ValidationException::withMessages([
                    'ticket_item_status' => __('ui.actions.departments.updatedepartmentticketitemstatusaction.u_vas_net_dos'),
                ]);
            }

            if (Gate::forUser($user)->denies('updateStatus', $item->kitchenTicket)) {
                throw ValidationException::withMessages([
                    'ticket_item_status' => __('ui.actions.departments.updatedepartmentticketitemstatusaction.u_vas_net_dos'),
                ]);
            }

            if ($item->served_at !== null) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::DepartmentAlreadyReady,
                    'ticket_item_status',
                    __('ui.actions.departments.updatedepartmentticketitemstatusaction.eta_poziciia'),
                );
            }

            if ($item->status === KitchenTicketItemStatus::Cancelled || $status === KitchenTicketItemStatus::Cancelled) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::OrderItemAlreadyCancelled,
                    'ticket_item_status',
                    __('orders.items.errors.kitchen_cancelled'),
                );
            }

            $order = $item->kitchenTicket->order;

            if ($order->status === OrderStatus::Cancelled) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::OrderAlreadyCancelled,
                    'ticket_item_status',
                    __('ui.actions.departments.updatedepartmentticketitemstatusaction.zakaz_otmenen'),
                );
            }

            if ($item->kitchenTicket->tableSession->status->locksOrderChanges()
                || in_array($order->status, [OrderStatus::Served, OrderStatus::PaymentRequested, OrderStatus::Paid, OrderStatus::Closed], true)) {
                throw ValidationException::withMessages(['ticket_item_status' => __('preparation.errors.order_finished')]);
            }

            if ($item->kitchenTicket->tableSession->branch_id !== $item->kitchenTicket->branch_id
                || $order->branch_id !== $item->kitchenTicket->branch_id
                || $order->table_session_id !== $item->kitchenTicket->table_session_id
                || ($expectedTicketId !== null && $item->kitchen_ticket_id !== $expectedTicketId)) {
                throw ValidationException::withMessages(['ticket_item_status' => __('preparation.errors.stale_item')]);
            }

            $previousStatus = $item->status;
            $previousUpdatedAt = (string) $item->getRawOriginal('updated_at');

            if ($previousStatus === $status) {
                $transitionEvent = $this->transitionEvent($item, $status);
            }

            if ($expectedStatus !== null
                && ($expectedStatus !== $previousStatus || $expectedUpdatedAt !== $previousUpdatedAt)
                && ! $this->isExactReplay($transitionEvent, $expectedStatus, $expectedUpdatedAt, $user)) {
                throw ValidationException::withMessages(['ticket_item_status' => __('preparation.errors.stale_item')]);
            }

            if ($previousStatus === $status) {
                return $item;
            }

            if (! $previousStatus->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'ticket_item_status' => __('errors.types.order_invalid_transition.message'),
                ]);
            }

            $updatedRows = KitchenTicketItem::query()
                ->whereKey($item->id)
                ->where('status', $previousStatus->value)
                ->where('updated_at', $previousUpdatedAt)
                ->where('kitchen_ticket_id', $item->kitchen_ticket_id)
                ->whereNull('served_at')
                ->update(['status' => $status]);

            if ($updatedRows === 0) {
                throw ValidationException::withMessages([
                    'ticket_item_status' => __('preparation.errors.stale_item'),
                ]);
            }

            $item->forceFill(['status' => $status]);

            $transitionEvent = $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::TicketItemStatusChanged,
                order: $order,
                actorUser: $user,
                previousStatus: $previousStatus,
                newStatus: $status,
                statusType: 'kitchen_ticket_item',
                metadata: [
                    'kitchen_ticket_id' => $item->kitchen_ticket_id,
                    'kitchen_ticket_item_id' => $item->id,
                    'order_item_id' => $item->order_item_id,
                    'department_name' => $item->kitchenTicket->department_name,
                    'command' => ['expected_status' => $previousStatus->value, 'expected_updated_at' => $previousUpdatedAt],
                    'notifications' => ['state' => in_array($status, [KitchenTicketItemStatus::InProgress, KitchenTicketItemStatus::Ready], true) ? 'pending' : 'not_required'],
                ],
            );

            if (! $transitionEvent->exists) {
                throw new RuntimeException('Preparation transition history was not persisted.');
            }

            $this->syncOrderStatus->handle($order, $user);

            if ($status === KitchenTicketItemStatus::Ready) {
                $this->recordAuditLog->handle(
                    action: AuditLogAction::DepartmentItemReady,
                    entityType: 'kitchen_ticket_item',
                    entityId: $item->id,
                    actorUser: $user,
                    organizationId: $item->kitchenTicket->branch->organization_id,
                    branchId: $item->kitchenTicket->branch_id,
                    oldValues: [
                        'status' => $previousStatus,
                    ],
                    newValues: [
                        'status' => KitchenTicketItemStatus::Ready,
                        'order_id' => $item->kitchenTicket->order_id,
                        'order_item_id' => $item->order_item_id,
                        'department_name' => $item->kitchenTicket->department_name,
                        'item_name' => $item->item_name,
                        'quantity' => $item->quantity,
                    ],
                );
            }

            return $item->refresh();
        }, attempts: 3);

        $notificationPending = false;
        if ($transitionEvent instanceof OrderStatusLog && data_get($transitionEvent->metadata, 'notifications.state') === 'pending') {
            try {
                $this->deliverNotifications->handle($transitionEvent->id);
            } catch (Throwable $exception) {
                report($exception);
                $notificationPending = true;
            }
        }

        return $item->refresh()->setAttribute('notification_delivery_pending', $notificationPending);
    }

    private function transitionEvent(KitchenTicketItem $item, KitchenTicketItemStatus $status): ?OrderStatusLog
    {
        return OrderStatusLog::query()
            ->select(['id', 'order_id', 'actor_user_id', 'new_status', 'previous_status', 'metadata', 'occurred_at'])
            ->where('order_id', $item->kitchenTicket->order_id)
            ->where('event', OrderStatusLogEvent::TicketItemStatusChanged->value)
            ->where('metadata->kitchen_ticket_item_id', $item->id)
            ->where('new_status', $status->value)
            ->latest('id')
            ->first();
    }

    private function isExactReplay(
        ?OrderStatusLog $event,
        KitchenTicketItemStatus $expectedStatus,
        ?string $expectedUpdatedAt,
        User $user,
    ): bool {
        return $event instanceof OrderStatusLog
            && $event->actor_user_id === $user->id
            && data_get($event->metadata, 'command.expected_status') === $expectedStatus->value
            && data_get($event->metadata, 'command.expected_updated_at') === $expectedUpdatedAt;
    }
}
