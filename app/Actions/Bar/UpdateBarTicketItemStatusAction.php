<?php

namespace App\Actions\Bar;

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenTicketItem;
use App\Models\User;

class UpdateBarTicketItemStatusAction
{
    public function __construct(
        private readonly UpdateDepartmentTicketItemStatusAction $updateDepartmentTicketItemStatus,
    ) {}

    public function handle(int $itemId, KitchenTicketItemStatus $status, User $user): KitchenTicketItem
    {
        return $this->updateDepartmentTicketItemStatus->handle(
            itemId: $itemId,
            status: $status,
            user: $user,
            departmentTypes: KitchenDepartmentType::barProductionTypes(),
            roleCodes: [SystemRole::Bartender, SystemRole::HeadChef],
            permissionCodes: [SystemPermission::ViewOrders, SystemPermission::SendToKitchen],
        );
    }
}
