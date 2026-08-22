<?php

declare(strict_types=1);

namespace App\Livewire\Restaurant;

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Actions\Exports\BuildDataExportsIndexAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $canAccessRestaurantDashboard = false;

    public bool $canAccessWaiterDashboard = false;

    public bool $canAccessKitchenDashboard = false;

    public bool $canAccessBarDashboard = false;

    public bool $canAccessAuditLog = false;

    public bool $canAccessDataExports = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $dashboard = null;

    public function mount(
        BuildRestaurantDashboardAction $buildRestaurantDashboard,
        BuildWaiterDashboardAction $buildWaiterDashboard,
        ResolveKitchenAccessibleDepartmentIdsAction $resolveKitchenDepartments,
        ResolveBarAccessibleDepartmentIdsAction $resolveBarDepartments,
        BuildAuditLogIndexAction $buildAuditLogIndex,
        BuildDataExportsIndexAction $buildDataExportsIndex,
    ): void {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $this->canAccessWaiterDashboard = $buildWaiterDashboard->userHasAccess($user);
        $this->canAccessKitchenDashboard = $resolveKitchenDepartments->userHasAccess($user);
        $this->canAccessBarDashboard = $resolveBarDepartments->userHasAccess($user);
        $this->canAccessAuditLog = $buildAuditLogIndex->userHasAccess($user);
        $this->canAccessDataExports = $buildDataExportsIndex->userHasAccess($user);

        $this->refreshDashboard($buildRestaurantDashboard);
    }

    public function refreshDashboard(BuildRestaurantDashboardAction $buildRestaurantDashboard): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            $this->canAccessRestaurantDashboard = false;
            $this->dashboard = null;

            return;
        }

        $payload = $buildRestaurantDashboard->handle($user);

        $this->canAccessRestaurantDashboard = (bool) $payload['has_access'];
        $this->dashboard = is_array($payload['dashboard'] ?? null) ? $payload['dashboard'] : null;
    }

    public function render(): View
    {
        return view('livewire.restaurant.dashboard', [
            'emptyStateFeatureKeys' => [
                'ui.pages.restaurant.dashboard.setup',
                'ui.pages.restaurant.dashboard.guest_flow',
                'ui.pages.restaurant.dashboard.waiter_workflow',
            ],
        ]);
    }
}
