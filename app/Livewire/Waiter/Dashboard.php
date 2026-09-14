<?php

declare(strict_types=1);

namespace App\Livewire\Waiter;

use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\MarkWaiterCallHandledAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\User;
use App\Services\Waiter\WaiterTableQueryService;
use App\Support\LocalizedDateFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    private BuildWaiterDashboardAction $buildWaiterDashboard;

    private WaiterTableQueryService $waiterQueries;

    private ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds;

    /**
     * @var list<array<string, mixed>>
     */
    public array $branches = [];

    public int $servicePointCount = 0;

    public int $activeSessionCount = 0;

    public int $newDraftCount = 0;

    public int $waiterCallCount = 0;

    public int $billRequestCount = 0;

    public int $readyItemCount = 0;

    /**
     * @var array{
     *     new_drafts: list<int>,
     *     waiter_calls: list<int>,
     *     bill_requests: list<int>,
     *     ready_items: list<int>
     * }|null
     */
    #[Locked]
    public ?array $knownWorkIds = null;

    public string $waiterCallMessage = '';

    public string $tableActionMessage = '';

    public string $refreshedAt = '';

    public string $zoneScope = 'mine';

    #[Url(as: 'table', history: true)]
    public ?int $selectedTableSessionId = null;

    #[Url(as: 'branch')]
    public ?int $selectedBranchId = null;

    public string $branchSearch = '';

    /** @var list<array{value: string, label: string}> */
    public array $branchOptions = [];

    #[Url(as: 'page', except: 1)]
    public int $tablePage = 1;

    #[Url(as: 'attention', except: false)]
    public bool $attentionOnly = false;

    public bool $hasMorePages = false;

    public int $pollingInterval = 1;

    public int $attentionCount = 0;

    public function boot(
        BuildWaiterDashboardAction $buildWaiterDashboard,
        WaiterTableQueryService $waiterQueries,
        ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ): void {
        $this->buildWaiterDashboard = $buildWaiterDashboard;
        $this->waiterQueries = $waiterQueries;
        $this->resolveAccessibleBranchIds = $resolveAccessibleBranchIds;
    }

    public function mount(): void
    {
        $this->refreshDashboard();
    }

    public function refreshDashboard(): void
    {
        $payload = $this->buildWaiterDashboard->handle($this->currentUser(), $this->normalizedZoneScope(), $this->selectedBranchId, $this->tablePage, $this->branchSearch, $this->attentionOnly);

        if (! $payload['has_access']) {
            abort(403);
        }

        $this->selectedBranchId = $payload['selected_branch_id'];
        $this->branchOptions = $payload['branch_options'];
        $this->tablePage = $payload['page'];
        $this->hasMorePages = $payload['has_more_pages'];
        $this->pollingInterval = $payload['polling_interval'];
        $this->attentionCount = $payload['attention_count'];

        $settingsBranchIds = $this->resolveAccessibleBranchIds->handle($this->currentUser(), SystemPermission::ManageSettings);
        $this->branches = array_map(fn (array $branch): array => [
            ...$branch,
            'can_manage_settings' => $settingsBranchIds->contains((int) $branch['id']),
        ], $payload['branches']);
        $this->servicePointCount = $payload['service_point_count'];
        $this->activeSessionCount = $payload['active_session_count'];
        $this->newDraftCount = $payload['new_draft_count'];
        $this->waiterCallCount = $payload['waiter_call_count'];
        $this->billRequestCount = $payload['bill_request_count'];
        $this->readyItemCount = $payload['ready_item_count'];
        $this->refreshedAt = LocalizedDateFormatter::timeWithSeconds(now()) ?? '';
        $this->normalizeSelectedTable();

        $currentWorkIds = $payload['work_ids'];

        if ($this->knownWorkIds !== null) {
            $this->dispatchNewWorkEvents($currentWorkIds, $this->knownWorkIds);
        }

        $this->knownWorkIds = $currentWorkIds;
    }

    public function setZoneScope(string $zoneScope): void
    {
        $this->zoneScope = $zoneScope === 'all' ? 'all' : 'mine';
        $this->tablePage = 1;
        $this->knownWorkIds = null;
        $this->refreshDashboard();
    }

    public function updatedSelectedBranchId(): void
    {
        $this->tablePage = 1;
        $this->knownWorkIds = null;
        $this->refreshDashboard();
    }

    public function updatedBranchSearch(): void
    {
        $this->refreshDashboard();
    }

    public function updatedAttentionOnly(): void
    {
        $this->tablePage = 1;
        $this->refreshDashboard();
    }

    public function changeTablePage(int $page): void
    {
        $this->tablePage = max(1, $page);
        $this->refreshDashboard();
    }

    public function selectTable(int $tableSessionId): void
    {
        $this->selectedTableSessionId = $this->visibleTableSummary($tableSessionId) === null
            ? null
            : $tableSessionId;
    }

    public function openTable(
        int $servicePointId,
        OpenTableSessionForServicePointAction $openTableSession,
        ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ): void {
        $user = $this->currentUser();
        $servicePoint = $this->waiterQueries->servicePoint($servicePointId);

        if ($servicePoint === null) {
            abort(404);
        }
        $openTableBranchIds = $resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ViewOrders)
            ->merge($resolveAccessibleBranchIds->handle($user, SystemPermission::ConfirmOrders))
            ->unique()
            ->values();

        if (! $openTableBranchIds->contains((int) $servicePoint->branch_id)) {
            abort(403);
        }

        try {
            $openTableSession->handle($servicePoint, $user);
            $this->tableActionMessage = __('ui.livewire.waiter.dashboard.stol_otkryt');
        } catch (ValidationException $exception) {
            $this->tableActionMessage = $this->firstValidationMessage($exception);
        }

        $this->refreshDashboard();
    }

    public function markWaiterCallHandled(int $waiterCallId, MarkWaiterCallHandledAction $markHandled): void
    {
        $waiterCall = $this->waiterQueries->waiterCall($waiterCallId);

        try {
            $markHandled->handle($waiterCall, $this->currentUser());
            $this->waiterCallMessage = __('ui.livewire.waiter.dashboard.vyzov_oficianta_otmecen_kak_obrabotannyi');
        } catch (ValidationException $exception) {
            $this->waiterCallMessage = $this->firstValidationMessage($exception);
        }

        $this->refreshDashboard();
    }

    public function disableTemporaryClosure(
        int $branchId,
        UpdateBranchTemporaryClosureAction $updateBranchTemporaryClosure,
        ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ): void {
        $user = $this->currentUser();
        $branchIds = $resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ManageSettings);

        if (! $branchIds->contains($branchId)) {
            abort(403);
        }

        $branch = $this->waiterQueries->branch($branchId);

        $updateBranchTemporaryClosure->handle($branch, false);
        $this->tableActionMessage = __('ui.livewire.waiter.dashboard.restoran_snova_otkryt_dlia_zakazov');

        $this->refreshDashboard();
    }

    public function render(): View
    {
        return view('livewire.waiter.dashboard', [
            'selectedTable' => $this->selectedTableSessionId === null
                ? null
                : $this->visibleTableSummary($this->selectedTableSessionId),
        ])
            ->title(__('ui.waiter.dashboard.waiter_dashboard'));
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $messages = collect($exception->errors())->flatten();

        return (string) ($messages->first() ?? __('ui.livewire.waiter.dashboard.ne_udalos_obrabotat_vyzov_oficianta'));
    }

    private function normalizedZoneScope(): string
    {
        if ($this->zoneScope !== 'all') {
            $this->zoneScope = 'mine';
        }

        return $this->zoneScope;
    }

    private function normalizeSelectedTable(): void
    {
        if ($this->selectedTableSessionId !== null && $this->visibleTableSummary($this->selectedTableSessionId) === null) {
            $this->selectedTableSessionId = null;
        }
    }

    /**
     * @return array{branch: array<string, mixed>, service_point: array<string, mixed>, session: array<string, mixed>}|null
     */
    private function visibleTableSummary(int $tableSessionId): ?array
    {
        foreach ($this->branches as $branch) {
            $servicePoints = $branch['service_points'] ?? [];

            if (! is_array($servicePoints)) {
                continue;
            }

            foreach ($servicePoints as $servicePoint) {
                if (! is_array($servicePoint)) {
                    continue;
                }

                $sessions = $servicePoint['sessions'] ?? [];

                if (! is_array($sessions)) {
                    continue;
                }

                foreach ($sessions as $session) {
                    if (is_array($session) && (int) ($session['id'] ?? 0) === $tableSessionId) {
                        return [
                            'branch' => $branch,
                            'service_point' => $servicePoint,
                            'session' => $session,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<int>>  $currentWorkIds
     * @param  array<string, list<int>>  $knownWorkIds
     */
    private function dispatchNewWorkEvents(array $currentWorkIds, array $knownWorkIds): void
    {
        $eventsByWorkType = [
            'new_drafts' => 'waiter-new-draft',
            'waiter_calls' => 'waiter-called',
            'bill_requests' => 'waiter-bill-requested',
            'ready_items' => 'waiter-item-ready',
        ];

        foreach ($eventsByWorkType as $workType => $eventName) {
            if (array_diff($currentWorkIds[$workType] ?? [], $knownWorkIds[$workType] ?? []) !== []) {
                $this->dispatch($eventName);
            }
        }
    }
}
