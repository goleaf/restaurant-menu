<?php

namespace App\Livewire\Bar;

use App\Enums\KitchenDepartmentType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Departments\Dashboard as DepartmentDashboard;
use Livewire\Attributes\Title;

#[Title('Bar screen')]
class Dashboard extends DepartmentDashboard
{
    /**
     * @return list<KitchenDepartmentType>
     */
    protected function departmentTypes(): array
    {
        return [KitchenDepartmentType::Bar];
    }

    /**
     * @return list<SystemRole>
     */
    protected function roleCodes(): array
    {
        return [SystemRole::Bartender, SystemRole::HeadChef];
    }

    /**
     * @return list<SystemPermission>
     */
    protected function permissionCodes(): array
    {
        return [SystemPermission::ViewOrders, SystemPermission::SendToKitchen];
    }

    protected function screenTitle(): string
    {
        return __('Bar screen');
    }

    protected function screenSubtitle(): string
    {
        return __('Bar drinks ready for service.');
    }

    protected function screenDataPage(): string
    {
        return 'bar-dashboard';
    }

    protected function screenEmptyMessage(): string
    {
        return __('departments.dashboard.no_orders');
    }

    protected function screenItemCountLabel(): string
    {
        return __('Drinks');
    }
}
