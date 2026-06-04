<?php

namespace App\Actions\Bar;

use App\Actions\Departments\BuildDepartmentDashboardAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\User;

class BuildBarDashboardAction
{
    public function __construct(
        private readonly BuildDepartmentDashboardAction $buildDepartmentDashboard,
    ) {}

    /**
     * @return array{
     *     has_access: bool,
     *     departments: list<array<string, mixed>>,
     *     selected_department_id: int|null,
     *     selected_department_name: string|null,
     *     tickets: list<array<string, mixed>>,
     *     ticket_count: int,
     *     new_item_count: int,
     *     in_progress_item_count: int,
     *     ready_item_count: int
     * }
     */
    public function handle(User $user, ?int $selectedDepartmentId = null): array
    {
        return $this->buildDepartmentDashboard->handle(
            user: $user,
            selectedDepartmentId: $selectedDepartmentId,
            departmentTypes: [KitchenDepartmentType::Bar],
            roleCodes: [SystemRole::Bartender, SystemRole::HeadChef],
            permissionCodes: [SystemPermission::ViewOrders, SystemPermission::SendToKitchen],
        );
    }

    public function userHasAccess(User $user): bool
    {
        return $this->buildDepartmentDashboard->userHasAccess(
            user: $user,
            departmentTypes: [KitchenDepartmentType::Bar],
            roleCodes: [SystemRole::Bartender, SystemRole::HeadChef],
            permissionCodes: [SystemPermission::ViewOrders, SystemPermission::SendToKitchen],
        );
    }
}
