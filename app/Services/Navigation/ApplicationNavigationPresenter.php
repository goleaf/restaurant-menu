<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\User;
use App\Services\Onboarding\RestaurantSetupQueryService;
use App\Support\Navigation\WorkspaceContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

final class ApplicationNavigationPresenter
{
    public function __construct(
        private readonly WorkspaceAccessQuery $access,
        private readonly WorkspaceContextResolver $resolver,
        private readonly RestaurantSetupQueryService $restaurantSetupQueries,
        private readonly Application $application,
    ) {}

    /** @return array<string, mixed> */
    public function handle(?Authenticatable $authenticatedUser, Request $request): array
    {
        $user = $authenticatedUser instanceof User ? $authenticatedUser : null;
        $access = $user ? $this->access->destinations($user) : [];
        $workspace = $user ? $this->resolver->resolve($user, $request, $access) : null;
        $items = $workspace ? $this->restaurantItems($workspace, $access) : [];
        $platform = $user?->isSuperadmin() ?? false;
        $onboarding = $user && $this->restaurantSetupQueries->userHasAccess($user);
        if ($user) {
            if ($items === []) {
                $items[] = $this->item('dashboard', 'workspace.choose', 'building-storefront', route('dashboard'), $request->routeIs('dashboard'));
            }
            $items[] = $this->item('organizations', 'workspace.manage_restaurants', 'building-office', route('restaurants.index'), $workspace->mode === 'structure', 'administration');
            if ($onboarding) {
                $items[] = $this->item('onboarding', 'navigation.onboarding', 'sparkles', route('restaurants.create'), $request->routeIs('onboarding.*', 'restaurants.create', 'restaurants.setup'), 'administration');
            }
            if ($platform) {
                $items[] = $this->item('superadmin', 'workspace.platform', 'rectangle-group', route('superadmin.dashboard'), $request->routeIs('superadmin.*'), 'administration');
                if ($this->application->environment('local')) {
                    $items[] = $this->item('components', 'ui.reference.title', 'swatch', route('local.components'), $request->routeIs('local.components'), 'administration');
                }
            }
        }

        return [
            'workspace' => $workspace,
            'workspaceFallback' => $request->query('workspace_notice') === 'section_unavailable',
            'navigationItems' => $items,
            'serviceNavigation' => array_values(array_filter($items, fn (array $item): bool => in_array($item['key'], ['waiter', 'kitchen', 'bar'], true))),
            'hallNavigation' => $workspace ? $this->hallItems($workspace, $access, $request) : [],
            'canAccessPlatformDashboard' => $platform,
            'canAccessOnboarding' => $onboarding,
            'authenticatedUser' => $user ? ['name' => $user->name, 'email' => $user->email, 'initials' => $user->initials()] : null,
        ];
    }

    /**
     * @param  array<string, list<int>>  $access
     * @return list<array{key: string, label: string, icon: string, href: string, current: bool, group: string, search: string}>
     */
    public function restaurantItems(WorkspaceContext $context, array $access): array
    {
        if ($context->branchId === null) {
            if ($context->mode !== 'aggregate') {
                return [];
            }
            $items = [];
            foreach (['overview' => 'restaurant.dashboard', 'reports' => 'restaurant.exports.index', 'audit' => 'restaurant.audit-log.index'] as $key => $route) {
                if (($access[$key] ?? []) !== [] || ($key === 'reports' && ($access['report_view'] ?? []) !== [])) {
                    $href = $key === 'reports' ? $this->aggregateDestination('reports', $access) : route($route, ['workspace' => 'all']);
                    $items[] = $this->item($key, 'workspace.'.$key, 'squares-2x2', $href, $context->destination === $key);
                }
            }

            return $items;
        }
        $id = $context->branchId;
        $allows = static fn (string $key): bool => in_array($id, $access[$key] ?? [], true);
        $nested = ['organization' => $context->organizationId, 'brand' => $context->brandId, 'branch' => $id];
        $hallsRoute = 'service-points.index';
        $definitions = [
            ['overview', 'squares-2x2', 'restaurant.dashboard', ['branch' => $id], $allows('overview')],
            ['waiter', 'clipboard-document-list', 'restaurant.waiter.dashboard', ['branch' => $id], $allows('waiter')],
            ['kitchen', 'fire', 'restaurant.kitchen.dashboard', ['branch' => $id], $allows('kitchen')],
            ['bar', 'beaker', 'restaurant.bar.dashboard', ['branch' => $id], $allows('bar')],
            ['menu', 'book-open', 'organizations.brands.branches.menu.index', $nested, $allows('menu')],
            ['availability', 'clock', 'organizations.brands.branches.availability.index', $nested, $allows('availability')],
            ['halls', 'qr-code', 'organizations.brands.branches.'.$hallsRoute, $nested, $allows('halls') || $allows('tables') || $allows('qr')],
            ['team', 'users', 'organizations.brands.branches.staff.index', $nested, $allows('team')],
            ['reports', 'arrow-down-tray', $allows('reports') ? 'restaurant.exports.index' : 'restaurant.dashboard', $allows('reports') ? ['branch' => $id] : ['branch' => $id, 'workspace_section' => 'reports'], $allows('reports') || $allows('report_view')],
            ['settings', 'cog-6-tooth', 'organizations.brands.branches.settings.index', $nested, $allows('settings')],
            ['audit', 'shield-check', 'restaurant.audit-log.index', ['branch' => $id], $allows('audit')],
        ];
        $items = [];
        foreach ($definitions as [$key, $icon, $route, $parameters, $allowed]) {
            if ($allowed) {
                $items[] = $this->item($key, 'workspace.'.$key, $icon, route($route, $parameters), $context->destination === $key);
            }
        }

        return $items;
    }

    /** @param array<string, list<int>> $access @return list<array<string, mixed>> */
    private function hallItems(WorkspaceContext $context, array $access, Request $request): array
    {
        if ($context->branchId === null || $context->destination !== 'halls') {
            return [];
        }

        return [];
    }

    /** @param array<string, list<int>> $access */
    public function aggregateDestination(string $destination, array $access): ?string
    {
        if ($destination === 'reports' && ($access['reports'] ?? []) === [] && ($access['report_view'] ?? []) !== []) {
            return route('restaurant.dashboard', ['workspace' => 'all', 'workspace_section' => 'reports']);
        }
        $route = match ($destination) {
            'overview' => 'restaurant.dashboard',
            'reports' => 'restaurant.exports.index',
            'audit' => 'restaurant.audit-log.index',
            default => null,
        };

        return $route !== null && ($access[$destination] ?? []) !== [] ? route($route, ['workspace' => 'all']) : null;
    }

    /** @param array<string, list<int>> $access @return array{href: string, destination: string, fallback: bool}|null */
    public function destination(WorkspaceContext $context, array $access, ?string $preferred = null): ?array
    {
        $items = $this->restaurantItems($context, $access);
        $keys = array_column($items, 'key');
        $key = $preferred !== null && in_array($preferred, $keys, true) ? $preferred : null;
        if ($key === null) {
            $order = in_array($context->branchId, $access['management'] ?? [], true)
                ? ['overview', 'menu', 'availability', 'team', 'settings', 'reports', 'waiter', 'kitchen', 'bar', 'halls', 'audit']
                : ['waiter', 'kitchen', 'bar', 'menu', 'availability', 'team', 'settings', 'overview', 'halls', 'reports', 'audit'];
            foreach ($order as $candidate) {
                if (in_array($candidate, $keys, true)) {
                    $key = $candidate;
                    break;
                }
            }
        }
        foreach ($items as $item) {
            if ($item['key'] === $key) {
                return ['href' => $item['href'], 'destination' => $key, 'fallback' => $preferred !== null && $preferred !== $key];
            }
        }

        return null;
    }

    /** @return array{key: string, label: string, icon: string, href: string, current: bool, group: string, search: string} */
    private function item(string $key, string $label, string $icon, string $href, bool $current, string $group = 'workspace'): array
    {
        $searchKey = 'workspace.search_terms.'.$key;

        return ['key' => $key, 'label' => __($label), 'icon' => $icon, 'href' => $href, 'current' => $current, 'group' => $group, 'search' => __($label).' '.__($searchKey)];
    }
}
