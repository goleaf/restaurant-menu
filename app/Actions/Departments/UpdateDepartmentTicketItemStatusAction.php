<?php

namespace App\Actions\Departments;

use App\Actions\Orders\SyncOrderStatusFromTicketItemsAction;
use App\Actions\Waiter\ResolveWaiterNotificationRecipientsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\User;
use App\Notifications\KitchenItemReadyNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class UpdateDepartmentTicketItemStatusAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
        private readonly SyncOrderStatusFromTicketItemsAction $syncOrderStatus,
        private readonly ResolveWaiterNotificationRecipientsAction $resolveWaiterRecipients,
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
            ->select(['id', 'kitchen_ticket_id', 'status', 'served_at'])
            ->with([
                'kitchenTicket' => fn ($query) => $query
                    ->select(['id', 'kitchen_department_id', 'order_id'])
                    ->with(['order' => fn ($orderQuery) => $orderQuery->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                        'table_session_id',
                        'draft_order_id',
                        'status',
                        'metadata',
                    ])]),
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
            throw ValidationException::withMessages([
                'ticket_item_status' => __('Эта позиция уже подана официантом.'),
            ]);
        }

        $previousStatus = $item->status;

        $item->forceFill(['status' => $status])->save();

        if ($item->kitchenTicket?->order instanceof Order) {
            $this->syncOrderStatus->handle($item->kitchenTicket->order, $user);
        }

        $item = $item->refresh();
        $this->notifyWaiterRecipientsForReadyItem($item, $previousStatus, $status);

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
}
