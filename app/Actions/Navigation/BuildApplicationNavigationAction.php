<?php

declare(strict_types=1);

namespace App\Actions\Navigation;

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Exports\BuildDataExportsIndexAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

final class BuildApplicationNavigationAction
{
    public function __construct(
        private readonly BuildWaiterDashboardAction $buildWaiterDashboard,
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $resolveKitchenDepartments,
        private readonly ResolveBarAccessibleDepartmentIdsAction $resolveBarDepartments,
        private readonly BuildAuditLogIndexAction $buildAuditLogIndex,
        private readonly BuildDataExportsIndexAction $buildDataExportsIndex,
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveWaiterBranches,
    ) {}

    /**
     * @return array{
     *     canAccessPlatformDashboard: bool,
     *     canAccessWaiterDashboard: bool,
     *     canAccessKitchenDashboard: bool,
     *     canAccessBarDashboard: bool,
     *     canAccessAuditLog: bool,
     *     canAccessDataExports: bool,
     *     canAccessQrLookup: bool,
     *     authenticatedUser: array{name: string, email: string, initials: string}|null,
     *     currentNavigation: array<string, bool>
     * }
     */
    public function handle(?Authenticatable $authenticatedUser, Request $request): array
    {
        $user = $authenticatedUser instanceof User ? $authenticatedUser : null;

        return [
            'canAccessPlatformDashboard' => $user?->isSuperadmin() ?? false,
            'canAccessWaiterDashboard' => $user instanceof User && $this->buildWaiterDashboard->userHasAccess($user),
            'canAccessKitchenDashboard' => $user instanceof User && $this->resolveKitchenDepartments->userHasAccess($user),
            'canAccessBarDashboard' => $user instanceof User && $this->resolveBarDepartments->userHasAccess($user),
            'canAccessAuditLog' => $user instanceof User && $this->buildAuditLogIndex->userHasAccess($user),
            'canAccessDataExports' => $user instanceof User && $this->buildDataExportsIndex->userHasAccess($user),
            'canAccessQrLookup' => $user instanceof User && $this->resolveWaiterBranches
                ->handle($user, SystemPermission::GenerateQr)
                ->isNotEmpty(),
            'authenticatedUser' => $user instanceof User ? [
                'name' => $user->name,
                'email' => $user->email,
                'initials' => $user->initials(),
            ] : null,
            'currentNavigation' => [
                'dashboard' => $request->routeIs('dashboard'),
                'organizations' => $request->routeIs('organizations.*'),
                'onboarding' => $request->routeIs('onboarding.*'),
                'restaurant_dashboard' => $request->routeIs('restaurant.dashboard'),
                'qr_lookup' => $request->routeIs('restaurant.qr-lookup.*'),
                'waiter' => $request->routeIs('restaurant.waiter.*'),
                'kitchen' => $request->routeIs('restaurant.kitchen.*'),
                'bar' => $request->routeIs('restaurant.bar.*'),
                'audit_log' => $request->routeIs('restaurant.audit-log.*'),
                'exports' => $request->routeIs('restaurant.exports.*'),
                'superadmin' => $request->routeIs('superadmin.*'),
                'profile' => $request->routeIs('profile.edit'),
            ],
        ];
    }
}
