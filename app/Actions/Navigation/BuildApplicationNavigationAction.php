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
use App\Services\Onboarding\RestaurantSetupQueryService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
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
        private readonly RestaurantSetupQueryService $restaurantSetupQueries,
        private readonly Application $application,
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
     *     canAccessOnboarding: bool,
     *     authenticatedUser: array{name: string, email: string, initials: string}|null,
     *     currentNavigation: array<string, bool>,
     *     navigationItems: list<array{key: string, label: string, icon: string, href: string, current: bool, group: string}>
     * }
     */
    public function handle(?Authenticatable $authenticatedUser, Request $request): array
    {
        $user = $authenticatedUser instanceof User ? $authenticatedUser : null;

        $context = [
            'canAccessPlatformDashboard' => $user?->isSuperadmin() ?? false,
            'canAccessWaiterDashboard' => $user instanceof User && $this->buildWaiterDashboard->userHasAccess($user),
            'canAccessKitchenDashboard' => $user instanceof User && $this->resolveKitchenDepartments->userHasAccess($user),
            'canAccessBarDashboard' => $user instanceof User && $this->resolveBarDepartments->userHasAccess($user),
            'canAccessAuditLog' => $user instanceof User && $this->buildAuditLogIndex->userHasAccess($user),
            'canAccessDataExports' => $user instanceof User && $this->buildDataExportsIndex->userHasAccess($user),
            'canAccessQrLookup' => $user instanceof User && $this->resolveWaiterBranches
                ->handle($user, SystemPermission::GenerateQr)
                ->isNotEmpty(),
            'canAccessOnboarding' => $user instanceof User && $this->restaurantSetupQueries->userHasAccess($user),
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
                'components' => $request->routeIs('local.components'),
            ],
        ];

        $context['navigationItems'] = $user instanceof User
            ? $this->navigationItems($context)
            : [];

        return $context;
    }

    /**
     * @param  array{canAccessPlatformDashboard: bool, canAccessWaiterDashboard: bool, canAccessKitchenDashboard: bool, canAccessBarDashboard: bool, canAccessAuditLog: bool, canAccessDataExports: bool, canAccessQrLookup: bool, canAccessOnboarding: bool, currentNavigation: array<string, bool>}  $context
     * @return list<array{key: string, label: string, icon: string, href: string, current: bool, group: string}>
     */
    private function navigationItems(array $context): array
    {
        $destinations = [
            ['dashboard', 'navigation.dashboard', 'home', 'dashboard', true, 'workspace'],
            ['organizations', 'navigation.organizations', 'building-office', 'organizations.index', true, 'workspace'],
            ['onboarding', 'navigation.onboarding', 'sparkles', 'onboarding.restaurant', $context['canAccessOnboarding'], 'workspace'],
            ['restaurant_dashboard', 'navigation.restaurant', 'squares-2x2', 'restaurant.dashboard', true, 'workspace'],
            ['qr_lookup', 'navigation.qr_codes', 'qr-code', 'restaurant.qr-lookup.index', $context['canAccessQrLookup'], 'workspace'],
            ['waiter', 'navigation.waiter', 'clipboard-document-list', 'restaurant.waiter.dashboard', $context['canAccessWaiterDashboard'], 'workspace'],
            ['kitchen', 'navigation.kitchen', 'fire', 'restaurant.kitchen.dashboard', $context['canAccessKitchenDashboard'], 'workspace'],
            ['bar', 'navigation.bar', 'beaker', 'restaurant.bar.dashboard', $context['canAccessBarDashboard'], 'workspace'],
            ['audit_log', 'navigation.audit_log', 'shield-check', 'restaurant.audit-log.index', $context['canAccessAuditLog'], 'workspace'],
            ['exports', 'navigation.exports', 'arrow-down-tray', 'restaurant.exports.index', $context['canAccessDataExports'], 'workspace'],
            ['superadmin', 'navigation.superadmin', 'rectangle-group', 'superadmin.dashboard', $context['canAccessPlatformDashboard'], 'workspace'],
            ['components', 'ui.reference.title', 'swatch', 'local.components', $context['canAccessPlatformDashboard'] && $this->application->environment('local'), 'workspace'],
            ['guest_area', 'navigation.guest_area', 'home', 'guest.home', true, 'account'],
            ['profile', 'navigation.settings', 'cog-6-tooth', 'profile.edit', true, 'account'],
        ];

        $items = [];

        foreach ($destinations as [$key, $label, $icon, $route, $allowed, $group]) {
            if (! $allowed) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => __($label),
                'icon' => $icon,
                'href' => route($route),
                'current' => $context['currentNavigation'][$key] ?? false,
                'group' => $group,
            ];
        }

        return $items;
    }
}
