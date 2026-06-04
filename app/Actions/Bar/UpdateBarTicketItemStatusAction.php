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

    public function handle(KitchenTicketItem $item, KitchenTicketItemStatus $status, User $user): KitchenTicketItem
    {
        return $this->updateDepartmentTicketItemStatus->handle(
            item: $item,
            status: $status,
            user: $user,
            departmentTypes: [KitchenDepartmentType::Bar],
            roleCodes: [SystemRole::Bartender, SystemRole::HeadChef],
            permissionCodes: [SystemPermission::ViewOrders, SystemPermission::SendToKitchen],
        );
    }
}
