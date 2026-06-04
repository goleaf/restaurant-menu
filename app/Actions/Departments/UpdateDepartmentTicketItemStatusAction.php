<?php

namespace App\Actions\Departments;

use App\Actions\Orders\SyncOrderStatusFromTicketItemsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateDepartmentTicketItemStatusAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
        private readonly SyncOrderStatusFromTicketItemsAction $syncOrderStatus,
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

        $item->forceFill(['status' => $status])->save();

        if ($item->kitchenTicket?->order instanceof Order) {
            $this->syncOrderStatus->handle($item->kitchenTicket->order, $user);
        }

        return $item->refresh();
    }
}
