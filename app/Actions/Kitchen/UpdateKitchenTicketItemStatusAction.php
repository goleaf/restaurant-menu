<?php

namespace App\Actions\Kitchen;

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenTicketItem;
use App\Models\User;

class UpdateKitchenTicketItemStatusAction
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
            departmentTypes: [],
            roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
            permissionCodes: [SystemPermission::ViewKitchen],
        );
    }
}
