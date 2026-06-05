<?php

namespace App\Livewire\Kitchen;

use App\Enums\KitchenDepartmentType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Departments\Dashboard as DepartmentDashboard;
use Livewire\Attributes\Title;

#[Title('Kitchen screen')]
class Dashboard extends DepartmentDashboard
{
    /**
     * @return list<KitchenDepartmentType>
     */
    protected function departmentTypes(): array
    {
        return [];
    }

    /**
     * @return list<SystemRole>
     */
    protected function roleCodes(): array
    {
        return [SystemRole::HeadChef, SystemRole::Cook];
    }

    /**
     * @return list<SystemPermission>
     */
    protected function permissionCodes(): array
    {
        return [SystemPermission::ViewKitchen];
    }

    protected function screenTitle(): string
    {
        return __('ui.livewire.kitchen.dashboard.kitchen_screen');
    }

    protected function screenSubtitle(): string
    {
        return __('ui.livewire.kitchen.dashboard.department_tickets_ready_for_work');
    }

    protected function screenDataPage(): string
    {
        return 'kitchen-dashboard';
    }

    protected function screenEmptyMessage(): string
    {
        return __('departments.dashboard.no_orders');
    }

    protected function screenItemCountLabel(): string
    {
        return __('reports.csv.items');
    }
}
