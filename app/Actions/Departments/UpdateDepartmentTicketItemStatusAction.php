<?php

namespace App\Actions\Departments;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Orders\SyncOrderStatusFromTicketItemsAction;
use App\Actions\Waiter\ResolveWaiterNotificationRecipientsAction;
use App\Enums\AuditLogAction;
use App\Enums\BusinessRuleCode;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Notifications\KitchenItemCookingNotification;
use App\Notifications\KitchenItemReadyNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class UpdateDepartmentTicketItemStatusAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
        private readonly SyncOrderStatusFromTicketItemsAction $syncOrderStatus,
        private readonly ResolveWaiterNotificationRecipientsAction $resolveWaiterRecipients,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /**
     * @param  list<KitchenDepartmentType>  $departmentTypes
     * @param  list<SystemRole>  $roleCodes
     * @param  list<SystemPermission>  $permissionCodes
     */
    public function handle(
        KitchenTicketItem $item,
        KitchenTicketItemStatus $status,
        User $user,
        array $departmentTypes,
        array $roleCodes,
        array $permissionCodes,
    ): KitchenTicketItem {
        $item = KitchenTicketItem::query()
            ->select(['id', 'kitchen_ticket_id', 'order_item_id', 'item_name', 'quantity', 'status', 'served_at'])
            ->with([
                'kitchenTicket' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'kitchen_department_id', 'department_name', 'order_id'])
                    ->with([
                        'branch:id,organization_id',
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
            ->whereKey($item->id)
            ->firstOrFail();

        $departmentId = $item->kitchenTicket?->kitchen_department_id;
        $accessibleDepartmentIds = $this->resolveAccessibleDepartmentIds->handle(
            user: $user,
            departmentTypes: $departmentTypes,
            roleCodes: $roleCodes,
            permissionCodes: $permissionCodes,
        );

        if ($departmentId === null || ! $accessibleDepartmentIds->contains((int) $departmentId)) {
            throw ValidationException::withMessages([
                'ticket_item_status' => __('У вас нет доступа к этому тикету.'),
            ]);
        }

        if ($item->served_at !== null) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::DepartmentAlreadyReady,
                'ticket_item_status',
                __('Эта позиция уже подана официантом.'),
            );
        }

        if ($item->kitchenTicket?->order instanceof Order
            && $item->kitchenTicket->order->status === OrderStatus::Cancelled) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::OrderAlreadyCancelled,
                'ticket_item_status',
                __('Заказ отменён. Кухня и бар больше не работают по нему.'),
            );
        }

        $previousStatus = $item->status;

        if ($previousStatus === KitchenTicketItemStatus::Ready && $status === KitchenTicketItemStatus::Ready) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::DepartmentAlreadyReady,
                'ticket_item_status',
                __('Позиция уже отмечена готовой.'),
            );
        }

        $item->forceFill(['status' => $status])->save();

        if ($item->kitchenTicket?->order instanceof Order) {
            $this->syncOrderStatus->handle($item->kitchenTicket->order, $user);
        }

        if ($status === KitchenTicketItemStatus::Ready && $previousStatus !== KitchenTicketItemStatus::Ready) {
            $this->recordAuditLog->handle(
                action: AuditLogAction::DepartmentItemReady,
                entityType: 'kitchen_ticket_item',
                entityId: $item->id,
                actorUser: $user,
                organizationId: $item->kitchenTicket?->branch?->organization_id,
                branchId: $item->kitchenTicket?->branch_id,
                oldValues: [
                    'status' => $previousStatus,
                ],
                newValues: [
                    'status' => KitchenTicketItemStatus::Ready,
                    'order_id' => $item->kitchenTicket?->order_id,
                    'order_item_id' => $item->order_item_id,
                    'department_name' => $item->kitchenTicket?->department_name,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                ],
            );
        }

        $item = $item->refresh();
        $this->notifyWaiterRecipientsForReadyItem($item, $previousStatus, $status);
        $this->notifyGuestRecipientForTicketItem($item, $previousStatus, $status);

        return $item->refresh();
    }

    private function notifyWaiterRecipientsForReadyItem(
        KitchenTicketItem $item,
        KitchenTicketItemStatus $previousStatus,
        KitchenTicketItemStatus $newStatus,
    ): void {
        if ($newStatus !== KitchenTicketItemStatus::Ready || $previousStatus === KitchenTicketItemStatus::Ready) {
            return;
        }

        $item = KitchenTicketItem::query()
            ->select([
                'id',
                'kitchen_ticket_id',
                'order_item_id',
                'table_session_guest_id',
                'menu_item_id',
                'guest_name',
                'item_name',
                'quantity',
                'status',
                'selected_modifiers',
                'comment',
                'updated_at',
            ])
            ->with([
                'kitchenTicket' => fn ($query) => $query
                    ->select([
                        'id',
                        'order_id',
                        'branch_id',
                        'service_point_id',
                        'table_session_id',
                        'kitchen_department_id',
                        'department_type',
                        'department_name',
                    ])
                    ->with([
                        'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'organization_id', 'name']),
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
            ])
            ->whereKey($item->id)
            ->firstOrFail();
        $branch = $item->kitchenTicket?->branch;

        if ($branch === null) {
            return;
        }

        $recipients = $this->resolveWaiterRecipients->handle($branch);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new KitchenItemReadyNotification($item));
    }

    private function notifyGuestRecipientForTicketItem(
        KitchenTicketItem $item,
        KitchenTicketItemStatus $previousStatus,
        KitchenTicketItemStatus $newStatus,
    ): void {
        if ($previousStatus === $newStatus || ! in_array($newStatus, [
            KitchenTicketItemStatus::InProgress,
            KitchenTicketItemStatus::Ready,
        ], true)) {
            return;
        }

        $item = KitchenTicketItem::query()
            ->select([
                'id',
                'kitchen_ticket_id',
                'order_item_id',
                'table_session_guest_id',
                'menu_item_id',
                'guest_name',
                'item_name',
                'quantity',
                'status',
                'selected_modifiers',
                'comment',
                'updated_at',
            ])
            ->with([
                'guest' => fn ($query) => $query->select([
                    'id',
                    'table_session_id',
                    'guest_name',
                    'guest_token',
                    'status',
                    'joined_at',
                    'left_at',
                ]),
                'kitchenTicket' => fn ($query) => $query
                    ->select([
                        'id',
                        'order_id',
                        'branch_id',
                        'service_point_id',
                        'table_session_id',
                        'kitchen_department_id',
                        'department_type',
                        'department_name',
                    ])
                    ->with([
                        'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'organization_id', 'name']),
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
            ])
            ->whereKey($item->id)
            ->firstOrFail();
        $guest = $item->guest;

        if (! $guest instanceof TableSessionGuest || $guest->status !== TableSessionGuestStatus::Active) {
            return;
        }

        if ($newStatus === KitchenTicketItemStatus::InProgress) {
            $guest->notify(new KitchenItemCookingNotification($item));

            return;
        }

        $guest->notify(new KitchenItemReadyNotification($item));
    }
}
