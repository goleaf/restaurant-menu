<?php

declare(strict_types=1);

namespace App\Services\Superadmin;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Organization;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;

final class SuperadminDashboardQueryService
{
    /** @return array{organizations: int, brands: int, branches: int, service_points: int, orders: int, users: int} */
    public function stats(): array
    {
        return [
            'organizations' => Organization::query()->count(),
            'brands' => Brand::query()->count(),
            'branches' => Branch::query()->count(),
            'service_points' => ServicePoint::query()->count(),
            'orders' => Order::query()->count(),
            'users' => User::query()->count(),
        ];
    }

    /** @return CursorPaginator<int, Organization> */
    public function organizations(): CursorPaginator
    {
        return Organization::query()
            ->select(['id', 'owner_user_id', 'name', 'created_at'])
            ->with([
                'owner' => fn ($query) => $query->select(['id', 'name', 'email']),
                'subscription' => fn ($query) => $query->select([
                    'id',
                    'organization_id',
                    'status',
                    'started_at',
                    'next_payment_at',
                    'payment_status',
                    'created_at',
                ]),
            ])
            ->withCount([
                'brands',
                'branches',
                'servicePoints',
                'orders',
                'branches as active_branches_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('id')
            ->cursorPaginate(10, ['id', 'owner_user_id', 'name', 'created_at'], 'organizationsCursor');
    }

    /** @return CursorPaginator<int, Brand> */
    public function brands(): CursorPaginator
    {
        return Brand::query()
            ->select(['id', 'organization_id', 'name', 'created_at'])
            ->with(['organization' => fn ($query) => $query->select(['id', 'name'])])
            ->orderBy('id')
            ->cursorPaginate(10, ['id', 'organization_id', 'name', 'created_at'], 'brandsCursor');
    }

    /** @return CursorPaginator<int, Branch> */
    public function branches(): CursorPaginator
    {
        return Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'city', 'country', 'is_active', 'created_at'])
            ->with([
                'organization' => fn ($query) => $query->select(['id', 'name']),
                'brand' => fn ($query) => $query->select(['id', 'name']),
            ])
            ->orderBy('id')
            ->cursorPaginate(10, ['id', 'organization_id', 'brand_id', 'name', 'city', 'country', 'is_active', 'created_at'], 'branchesCursor');
    }

    /** @return CursorPaginator<int, User> */
    public function users(): CursorPaginator
    {
        return User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->with(['roles' => fn ($query) => $query->select(['roles.id', 'roles.code', 'roles.name'])])
            ->orderBy('id')
            ->cursorPaginate(10, ['id', 'name', 'email', 'created_at'], 'usersCursor');
    }

    public function findOrganization(int $organizationId): Organization
    {
        return Organization::query()
            ->select(['id', 'owner_user_id', 'name', 'created_at'])
            ->whereKey($organizationId)
            ->firstOrFail();
    }
}
