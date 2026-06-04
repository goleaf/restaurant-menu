<?php

namespace App\Livewire\Superadmin;

use App\Actions\Subscriptions\SetOrganizationSubscriptionStatusAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Organization;
use App\Models\ServicePoint;
use App\Models\User;
use Flux\Flux;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Platform dashboard')]
class Dashboard extends Component
{
    use WithPagination;

    /**
     * @return array{organizations: int, brands: int, branches: int, service_points: int, orders: int, users: int}
     */
    #[Computed]
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

    /**
     * @return CursorPaginator<int, Organization>
     */
    #[Computed]
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

    public function activateOrganization(
        int $organizationId,
        SetOrganizationSubscriptionStatusAction $setOrganizationSubscriptionStatus,
    ): void {
        $this->authorizeSuperadmin();

        $organization = $this->findOrganization($organizationId);
        $setOrganizationSubscriptionStatus->handle($organization, OrganizationSubscriptionStatus::Active);

        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('Organization activated.'));
    }

    public function suspendOrganization(
        int $organizationId,
        SetOrganizationSubscriptionStatusAction $setOrganizationSubscriptionStatus,
    ): void {
        $this->authorizeSuperadmin();

        $organization = $this->findOrganization($organizationId);
        $setOrganizationSubscriptionStatus->handle($organization, OrganizationSubscriptionStatus::Inactive);

        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('Organization suspended.'));
    }

    /**
     * @return CursorPaginator<int, Brand>
     */
    #[Computed]
    public function brands(): CursorPaginator
    {
        return Brand::query()
            ->select(['id', 'organization_id', 'name', 'created_at'])
            ->with(['organization' => fn ($query) => $query->select(['id', 'name'])])
            ->orderBy('id')
            ->cursorPaginate(10, ['id', 'organization_id', 'name', 'created_at'], 'brandsCursor');
    }

    /**
     * @return CursorPaginator<int, Branch>
     */
    #[Computed]
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

    /**
     * @return CursorPaginator<int, User>
     */
    #[Computed]
    public function users(): CursorPaginator
    {
        return User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->with(['roles' => fn ($query) => $query->select(['roles.id', 'roles.code', 'roles.name'])])
            ->orderBy('id')
            ->cursorPaginate(10, ['id', 'name', 'email', 'created_at'], 'usersCursor');
    }

    public function render(): View
    {
        return view('livewire.superadmin.dashboard');
    }

    private function findOrganization(int $organizationId): Organization
    {
        return Organization::query()
            ->select(['id', 'owner_user_id', 'name', 'created_at'])
            ->whereKey($organizationId)
            ->firstOrFail();
    }

    private function authorizeSuperadmin(): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isSuperadmin()) {
            abort(403);
        }
    }
}
