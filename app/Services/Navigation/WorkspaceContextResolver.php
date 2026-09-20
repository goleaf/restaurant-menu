<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Navigation\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class WorkspaceContextResolver
{
    public function __construct(private readonly WorkspaceAccessQuery $access) {}

    /** @param array<string, list<int>>|null $destinations */
    public function resolve(User $user, Request $request, ?array $destinations = null, ?string $pageDestination = null): WorkspaceContext
    {
        $destination = $pageDestination ?? $this->destination($request);
        $route = $request->route();
        $branch = $route?->parameter('branch');
        $scope = $request->query('workspace');
        abort_if($scope !== null && $scope !== 'all', 422);
        $mode = match (true) {
            $request->routeIs('superadmin.*', 'local.components') => 'platform',
            $request->routeIs('organizations.*', 'onboarding.*', 'restaurants.*')
                && ! ($request->routeIs('organizations.brands.branches.*') && $branch !== null) => 'structure',
            ! $request->routeIs('dashboard', 'restaurant.*', 'organizations.brands.branches.*')
                && ($pageDestination === null || $route?->getName() !== null) => 'none',
            default => null,
        };
        if ($mode !== null) {
            abort_if($scope === 'all', 403);

            return new WorkspaceContext($user->id, $mode, $destination);
        }
        $routeId = $branch instanceof Branch ? $branch->id : $this->identifier($branch);
        $object = $route?->parameter('tableSession') ?? $route?->parameter('servicePoint');
        $objectId = $object instanceof TableSession || $object instanceof ServicePoint ? $object->branch_id : null;
        if ($objectId === null && $object !== null) {
            $id = $this->identifier($object);
            $objectId = $route?->parameter('tableSession') !== null
                ? TableSession::query()->whereKey($id)->value('branch_id')
                : ServicePoint::query()->whereKey($id)->value('branch_id');
            abort_if($objectId === null, 404);
        }
        $queryId = $this->identifier($request->query('branch'));
        $department = in_array($destination, ['kitchen', 'bar', 'preparation'], true) && $request->query('department') === 'all'
            ? null : $this->identifier($request->query('department'));
        if ($request->routeIs('restaurant.preparation.*') && $request->query('ticket') !== null) {
            $ticketId = $this->identifier($request->query('ticket'));
            $objectId = KitchenTicket::query()->whereKey($ticketId)->value('branch_id');
            abort_if($objectId === null, 404);
        }
        if ($department !== null && $request->routeIs('restaurant.kitchen.*', 'restaurant.bar.*', 'restaurant.preparation.*')) {
            $departmentBranch = KitchenDepartment::query()->whereKey($department)->value('branch_id');
            abort_if($departmentBranch === null, 404);
            abort_if($objectId !== null && $objectId !== $departmentBranch, 409);
            $objectId = $departmentBranch;
        }
        $ids = array_values(array_unique(array_filter([$objectId, $routeId, $queryId], fn ($id) => $id !== null)));
        abort_if(count($ids) > 1, 409);
        $access = $destinations ?? $this->access->destinations($user);
        if ($scope === 'all') {
            abort_if($ids !== [], 409);
            abort_unless($pageDestination !== null || $request->routeIs('dashboard', 'restaurant.dashboard', 'restaurant.exports.index', 'restaurant.audit-log.index'), 403);
            abort_unless(in_array($destination, ['overview', 'reports', 'audit'], true) && (($access[$destination] ?? []) !== [] || ($destination === 'reports' && ($access['report_view'] ?? []) !== [])), 403);

            return new WorkspaceContext($user->id, 'aggregate', $destination);
        }
        if ($ids !== []) {
            $selected = $this->access->branch($ids[0], $access);
            foreach (['organization' => $selected->organization_id, 'brand' => $selected->brand_id] as $key => $expected) {
                $parent = $route?->parameter($key);
                $actual = $parent instanceof Model ? $parent->getKey() : $this->identifier($parent);
                abort_if($actual !== null && $actual !== $expected, 404);
            }

            return $this->restaurant($user, $selected, $destination);
        }
        if ($request->routeIs('restaurant.qr-lookup.*')) {
            return new WorkspaceContext($user->id, 'aggregate', 'halls');
        }
        $available = in_array($destination, ['waiter', 'kitchen', 'bar', 'preparation'], true)
            ? ($access[$destination] ?? []) : $this->access->branchIds($access);
        abort_if(in_array($destination, ['waiter', 'kitchen', 'bar', 'preparation'], true) && $available === [], 403);
        $preference = $request->hasSession() ? $request->session()->get('workspace.preference', []) : [];
        if ($request->routeIs('dashboard') && is_array($preference) && ($preference['actor'] ?? null) === $user->id
            && ($preference['mode'] ?? null) === 'aggregate' && in_array($preference['destination'] ?? null, ['overview', 'reports', 'audit'], true)
            && (($access[$preference['destination']] ?? []) !== [] || ($preference['destination'] === 'reports' && ($access['report_view'] ?? []) !== []))) {
            return new WorkspaceContext($user->id, 'aggregate', $preference['destination']);
        }
        $preferred = is_array($preference) && ($preference['actor'] ?? null) === $user->id ? ($preference['branch'] ?? null) : null;
        if (is_int($preferred) && in_array($preferred, $available, true)) {
            return $this->restaurant($user, $this->access->branch($preferred, $access), $destination);
        }
        if (count($available) === 1) {
            return $this->restaurant($user, $this->access->branch($available[0], $access), $destination);
        }

        return new WorkspaceContext($user->id, $request->routeIs('restaurant.dashboard', 'restaurant.exports.index', 'restaurant.audit-log.index') && $available !== [] ? 'aggregate' : 'none', $destination);
    }

    public function identifier(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = is_int($value) || is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        abort_if($id === false, 422);

        return $id;
    }

    public function destination(Request $request): string
    {
        return match (true) {
            $request->routeIs('restaurant.dashboard') && $request->query('workspace_section') === 'reports' => 'reports',
            $request->routeIs('organizations.brands.branches.menu.*') => 'menu',
            $request->routeIs('organizations.brands.branches.availability.*') => 'availability',
            $request->routeIs('organizations.brands.branches.staff.*') => 'team',
            $request->routeIs('organizations.brands.branches.settings.*') => 'settings',
            $request->routeIs('organizations.brands.branches.areas.*', 'organizations.brands.branches.service-points.*', 'organizations.brands.branches.qr.*') => 'halls',
            $request->routeIs('restaurant.qr-lookup.*') => 'halls',
            $request->routeIs('restaurant.waiter.*') => 'waiter',
            $request->routeIs('restaurant.preparation.*') => 'preparation',
            $request->routeIs('restaurant.kitchen.*') => 'kitchen',
            $request->routeIs('restaurant.bar.*') => 'bar',
            $request->routeIs('restaurant.exports.*') => 'reports',
            $request->routeIs('restaurant.audit-log.*') => 'audit',
            default => 'overview',
        };
    }

    private function restaurant(User $user, Branch $branch, string $destination): WorkspaceContext
    {
        return new WorkspaceContext($user->id, 'restaurant', $destination, $branch->id, $branch->organization_id, $branch->brand_id, $branch->name, $this->access->description($branch));
    }
}
