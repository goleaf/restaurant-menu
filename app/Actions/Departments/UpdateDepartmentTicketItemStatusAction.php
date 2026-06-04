<?php

namespace App\Actions\Departments;

use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenTicketItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateDepartmentTicketItemStatusAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
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
            ->select(['id', 'kitchen_ticket_id', 'status'])
            ->with([
                'kitchenTicket' => fn ($query) => $query->select(['id', 'kitchen_department_id']),
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

        $item->forceFill(['status' => $status])->save();

        return $item->refresh();
    }
}
