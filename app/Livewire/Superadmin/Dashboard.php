<?php

declare(strict_types=1);

namespace App\Livewire\Superadmin;

use App\Actions\Subscriptions\SetOrganizationSubscriptionStatusAction;
use App\Actions\System\BuildProductionSafetyReportAction;
use App\Actions\TableSessions\CleanupInactiveTableSessionsAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\PlainText;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Dashboard extends Component
{
    use WithPagination;

    private BuildProductionSafetyReportAction $buildProductionSafetyReport;

    public string $cleanupMessage = '';

    public string $organizationSuspendReason = '';

    public string $backupDownloadConfirmation = '';

    public string $backupDownloadReason = '';

    public function boot(BuildProductionSafetyReportAction $buildProductionSafetyReport): void
    {
        $this->buildProductionSafetyReport = $buildProductionSafetyReport;
    }

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
     * @return array{
     *     environment: string,
     *     environment_label: string,
     *     is_production: bool,
     *     warnings: list<array{code: string, message: string, severity: string}>
     * }
     */
    #[Computed]
    public function productionSafetyReport(): array
    {
        return $this->buildProductionSafetyReport->handle();
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
        $setOrganizationSubscriptionStatus->handle(
            organization: $organization,
            status: OrganizationSubscriptionStatus::Active,
            changedBy: $this->currentUser(),
        );

        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('ui.livewire.superadmin.dashboard.organization_activated'));
    }

    public function suspendOrganization(
        int $organizationId,
        SetOrganizationSubscriptionStatusAction $setOrganizationSubscriptionStatus,
    ): void {
        $this->authorizeSuperadmin();

        $validated = $this->validate([
            'organizationSuspendReason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'organizationSuspendReason.required' => __('ui.livewire.superadmin.dashboard.explain_why_this_organization_is_being_sus'),
            'organizationSuspendReason.min' => __('ui.livewire.organizations.brands.branches.index.the_suspension_reason_must'),
        ]);

        $organization = $this->findOrganization($organizationId);
        $setOrganizationSubscriptionStatus->handle(
            organization: $organization,
            status: OrganizationSubscriptionStatus::Inactive,
            changedBy: $this->currentUser(),
            reason: (string) $validated['organizationSuspendReason'],
        );

        $this->organizationSuspendReason = '';
        unset($this->organizations);

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('ui.livewire.superadmin.dashboard.organization_suspended'));
    }

    public function runSessionInactivityCleanup(CleanupInactiveTableSessionsAction $cleanupInactiveTableSessions): void
    {
        $this->authorizeSuperadmin();

        $result = $cleanupInactiveTableSessions->handle();
        $this->cleanupMessage = $this->cleanupSummary($result);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.settings.session_cleanup_finished'));
    }

    public function downloadBackup(): void
    {
        $this->authorizeSuperadmin();

        $this->backupDownloadReason = trim($this->backupDownloadReason);
        $validated = $this->validate([
            ...RestaurantValidationRules::auditReason('backupDownloadReason'),
            'backupDownloadConfirmation' => ['required', 'string', 'in:BACKUP'],
        ], [
            'backupDownloadReason.required' => __('ui.confirmations.reason.required'),
            'backupDownloadReason.min' => __('ui.confirmations.reason.min'),
            'backupDownloadConfirmation.required' => __('ui.confirmations.download_backup.confirmation_required'),
            'backupDownloadConfirmation.in' => __('ui.confirmations.download_backup.confirmation_match'),
        ]);

        session()->put('sqlite_backup_download_authorization', [
            'issued_at' => now()->timestamp,
            'reason' => PlainText::required((string) $validated['backupDownloadReason'], 500),
            'user_id' => $this->currentUser()->id,
        ]);

        $this->reset('backupDownloadConfirmation', 'backupDownloadReason');
        Flux::modals()->close();

        $this->redirectRoute('superadmin.backups.sqlite.download');
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
        $organizations = $this->organizations();
        $brands = $this->brands();
        $branches = $this->branches();
        $users = $this->users();

        return view('livewire.superadmin.dashboard', [
            'productionSafetyReport' => $this->productionSafetyReport(),
            'statRows' => $this->statRows(),
            'organizationRows' => $organizations->getCollection()
                ->map(fn (Organization $organization): array => $this->presentOrganization($organization))
                ->all(),
            'organizationPaginator' => $organizations,
            'brandRows' => $brands->getCollection()
                ->map(fn (Brand $brand): array => $this->presentBrand($brand))
                ->all(),
            'brandPaginator' => $brands,
            'branchRows' => $branches->getCollection()
                ->map(fn (Branch $branch): array => $this->presentBranch($branch))
                ->all(),
            'branchPaginator' => $branches,
            'userRows' => $users->getCollection()
                ->map(fn (User $user): array => $this->presentUser($user))
                ->all(),
            'userPaginator' => $users,
        ])->title(__('ui.superadmin.dashboard.platform_dashboard'));
    }

    /**
     * @return list<array{key: string, label: string, value: int}>
     */
    private function statRows(): array
    {
        $stats = $this->stats();

        return [
            ['key' => 'organizations', 'label' => __('navigation.organizations'), 'value' => $stats['organizations']],
            ['key' => 'brands', 'label' => __('navigation.brands'), 'value' => $stats['brands']],
            ['key' => 'branches', 'label' => __('navigation.branches'), 'value' => $stats['branches']],
            ['key' => 'service_points', 'label' => __('navigation.service_points'), 'value' => $stats['service_points']],
            ['key' => 'orders', 'label' => __('navigation.orders'), 'value' => $stats['orders']],
            ['key' => 'users', 'label' => __('ui.superadmin.dashboard.users'), 'value' => $stats['users']],
        ];
    }

    /**
     * @return array{id: int, name: string, owner_email: string, has_subscription: bool, subscription_color: string, subscription_label: string, is_active: bool, started_at: string, next_payment_at: string, payment_label: string, branches_count: int, active_branches_count: int, service_points_count: int, orders_count: int, brands_count: int, details_url: string, audit_url: string}
     */
    private function presentOrganization(Organization $organization): array
    {
        $subscriptionRelation = $organization->getRelation('subscription');
        $subscription = $subscriptionRelation instanceof OrganizationSubscription ? $subscriptionRelation : null;
        $ownerRelation = $organization->getRelation('owner');
        $owner = $ownerRelation instanceof User ? $ownerRelation : null;

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'owner_email' => $owner instanceof User
                ? $owner->email
                : __('ui.superadmin.dashboard.no_owner'),
            'has_subscription' => $subscription !== null,
            'subscription_color' => $subscription?->status->badgeColor() ?? 'amber',
            'subscription_label' => $subscription === null
                ? __('ui.superadmin.dashboard.subscription_not_initialized')
                : __($subscription->status->label()),
            'is_active' => $subscription?->status === OrganizationSubscriptionStatus::Active,
            'started_at' => $subscription?->started_at?->format('Y-m-d') ?? __('qr.labels.not_set'),
            'next_payment_at' => $subscription?->next_payment_at?->format('Y-m-d') ?? __('qr.labels.not_set'),
            'payment_label' => $subscription === null
                ? __('staff.invitation_statuses.pending')
                : __($subscription->payment_status->label()),
            'branches_count' => $organization->branches_count,
            'active_branches_count' => $organization->active_branches_count,
            'service_points_count' => $organization->service_points_count,
            'orders_count' => $organization->orders_count,
            'brands_count' => $organization->brands_count,
            'details_url' => route('organizations.brands.index', $organization),
            'audit_url' => route('restaurant.audit-log.index', ['organization' => $organization->id]),
        ];
    }

    /**
     * @return array{id: int, name: string, organization_name: string}
     */
    private function presentBrand(Brand $brand): array
    {
        $organizationRelation = $brand->getRelation('organization');

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'organization_name' => $organizationRelation instanceof Organization
                ? $organizationRelation->name
                : __('ui.superadmin.dashboard.no_organization'),
        ];
    }

    /**
     * @return array{id: int, name: string, is_active: bool, organization_name: string, brand_name: string, location: string}
     */
    private function presentBranch(Branch $branch): array
    {
        $organizationRelation = $branch->getRelation('organization');
        $brandRelation = $branch->getRelation('brand');

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'is_active' => $branch->is_active,
            'organization_name' => $organizationRelation instanceof Organization
                ? $organizationRelation->name
                : __('ui.superadmin.dashboard.no_organization'),
            'brand_name' => $brandRelation instanceof Brand
                ? $brandRelation->name
                : __('ui.superadmin.dashboard.no_brand'),
            'location' => collect([$branch->city, $branch->country])->filter()->join(', '),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, roles: string}
     */
    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->join(', ') ?: __('ui.superadmin.dashboard.no_roles'),
        ];
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
        $this->currentUser();
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isSuperadmin()) {
            abort(403);
        }

        return $user;
    }

    /**
     * @param  array{
     *     checked: int,
     *     pending_cancelled: int,
     *     active_warnings: int,
     *     skipped_unpaid_orders: int,
     *     skipped_existing_orders: int,
     *     skipped_existing_drafts: int
     * }  $result
     */
    private function cleanupSummary(array $result): string
    {
        return __(
            'ui.livewire.organizations.brands.branches.settings.cleanup_checked_sessions',
            [
                'checked' => $result['checked'],
                'cancelled' => $result['pending_cancelled'],
                'warnings' => $result['active_warnings'],
                'unpaid' => $result['skipped_unpaid_orders'],
            ],
        );
    }
}
