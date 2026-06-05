<?php

namespace App\Livewire\Superadmin;

use App\Actions\Subscriptions\SetOrganizationSubscriptionStatusAction;
use App\Actions\System\BuildProductionSafetyReportAction;
use App\Actions\TableSessions\CleanupInactiveTableSessionsAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Organization;
use App\Models\ServicePoint;
use App\Models\User;
use Flux\Flux;
use Illuminate\Http\RedirectResponse;
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

    public string $cleanupMessage = '';

    public string $organizationSuspendReason = '';

    public string $backupDownloadConfirmation = '';

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
        return app(BuildProductionSafetyReportAction::class)->handle();
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

        Flux::toast(variant: 'success', text: __('Organization activated.'));
    }

    public function suspendOrganization(
        int $organizationId,
        SetOrganizationSubscriptionStatusAction $setOrganizationSubscriptionStatus,
    ): void {
        $this->authorizeSuperadmin();

        $validated = $this->validate([
            'organizationSuspendReason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'organizationSuspendReason.required' => __('Explain why this organization is being suspended.'),
            'organizationSuspendReason.min' => __('The suspension reason must be clear enough for the audit log.'),
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
        Flux::toast(variant: 'success', text: __('Organization suspended.'));
    }

    public function runSessionInactivityCleanup(CleanupInactiveTableSessionsAction $cleanupInactiveTableSessions): void
    {
        $this->authorizeSuperadmin();

        $result = $cleanupInactiveTableSessions->handle();
        $this->cleanupMessage = $this->cleanupSummary($result);

        Flux::toast(variant: 'success', text: __('Session cleanup finished.'));
    }

    public function downloadBackup(): RedirectResponse
    {
        $this->authorizeSuperadmin();

        $this->validate([
            'backupDownloadConfirmation' => ['required', 'string', 'in:BACKUP'],
        ], [
            'backupDownloadConfirmation.required' => __('ui.confirmations.download_backup.confirmation_required'),
            'backupDownloadConfirmation.in' => __('ui.confirmations.download_backup.confirmation_match'),
        ]);

        $this->backupDownloadConfirmation = '';
        Flux::modals()->close();

        return redirect()->route('superadmin.backups.sqlite.download');
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
            'Cleanup checked :checked sessions. Cancelled :cancelled stale pending sessions. Active warnings: :warnings. Skipped with unpaid orders: :unpaid.',
            [
                'checked' => $result['checked'],
                'cancelled' => $result['pending_cancelled'],
                'warnings' => $result['active_warnings'],
                'unpaid' => $result['skipped_unpaid_orders'],
            ],
        );
    }
}
