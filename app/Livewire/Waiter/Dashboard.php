<?php

declare(strict_types=1);

namespace App\Livewire\Waiter;

use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\MarkWaiterCallHandledAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\User;
use App\Services\Navigation\WorkspaceContextResolver;
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
    #[Locked]
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

    #[Url(as: 'zone', history: true, except: 'mine')]
    public mixed $zoneScope = 'mine';

    #[Url(as: 'table', history: true)]
    public mixed $selectedTableSessionId = null;

    #[Locked]
    public mixed $selectedBranchId = null;

    #[Url(as: 'page', history: true, except: 1)]
    public mixed $tablePage = 1;

    public bool $attentionOnly = false;

    #[Url(as: 'attention', history: true, except: 'all')]
    public mixed $attention = 'all';

    private bool $responseAuthorized = false;

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

    public function mount(WorkspaceContextResolver $resolver): void
    {
        $context = $resolver->resolve($this->currentUser(), request(), pageDestination: 'waiter');
        $this->selectedBranchId = $context->branchId;
        if ($this->selectedBranchId === null) {
            $this->redirectRoute('dashboard');

            return;
        }
        $this->refreshDashboard();
    }

    public function refreshDashboard(): void
    {
        $this->validateSelections();
        $payload = $this->buildWaiterDashboard->handle($this->currentUser(), $this->normalizedZoneScope(), $this->selectedBranchId, $this->tablePage, '', $this->attentionOnly, $this->attention, $this->selectedTableSessionId);

        if (! $payload['has_access']) {
            abort(403);
        }

        $this->selectedBranchId = $payload['selected_branch_id'];
        $this->responseAuthorized = true;
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

    public function setZoneScope(mixed $zoneScope): void
    {
        abort_unless(is_string($zoneScope) && in_array($zoneScope, ['mine', 'all'], true), 422);
        $this->zoneScope = $zoneScope;
        $this->selectedTableSessionId = null;
        $this->tablePage = 1;
        $this->knownWorkIds = null;
        $this->refreshDashboard();
    }

    public function updatedAttention(): void
    {
        $this->attentionOnly = false;
        $this->selectedTableSessionId = null;
        $this->tablePage = 1;
        $this->refreshDashboard();
    }

    public function resetAttention(): void
    {
        $this->attention = 'all';
        $this->updatedAttention();
    }

    public function updatedSelectedTableSessionId(): void
    {
        $this->refreshDashboard();
    }

    public function updatedZoneScope(): void
    {
        $this->selectedTableSessionId = null;
        $this->tablePage = 1;
        $this->refreshDashboard();
    }

    public function updatedTablePage(): void
    {
        $this->selectedTableSessionId = null;
        $this->refreshDashboard();
    }

    public function updatedAttentionOnly(): void
    {
        $this->tablePage = 1;
        $this->refreshDashboard();
    }

    public function changeTablePage(mixed $page): void
    {
        $this->selectedTableSessionId = null;
        $this->tablePage = $this->positiveIdentifier($page);
        $this->refreshDashboard();
    }

    public function selectTable(mixed $tableSessionId): void
    {
        $tableSessionId = $this->positiveIdentifier($tableSessionId);
        $this->selectedTableSessionId = $this->visibleTableSummary($tableSessionId) === null
            ? null
            : $tableSessionId;
    }

    public function openTable(
        mixed $servicePointId,
        OpenTableSessionForServicePointAction $openTableSession,
        ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ): void {
        $user = $this->currentUser();
        $servicePoint = $this->waiterQueries->servicePoint($this->positiveIdentifier($servicePointId));

        if ($servicePoint === null) {
            abort(404);
        }
        abort_unless($servicePoint->branch_id === $this->selectedBranchId, 403);
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

    public function markWaiterCallHandled(mixed $waiterCallId, MarkWaiterCallHandledAction $markHandled): void
    {
        $waiterCall = $this->waiterQueries->waiterCall($this->positiveIdentifier($waiterCallId));

        abort_unless($waiterCall->servicePoint->branch_id === $this->selectedBranchId, 403);
        try {
            $markHandled->handle($waiterCall, $this->currentUser());
            $this->waiterCallMessage = __('ui.livewire.waiter.dashboard.vyzov_oficianta_otmecen_kak_obrabotannyi');
        } catch (ValidationException $exception) {
            $this->waiterCallMessage = $this->firstValidationMessage($exception);
        }

        $this->refreshDashboard();
    }

    public function render(): View
    {
        $this->validateSelections();
        if (! $this->responseAuthorized) {
            $permissions = $this->resolveAccessibleBranchIds->handleMany($this->currentUser(), [SystemPermission::ViewOrders, SystemPermission::ManageSettings]);
            abort_unless($permissions[SystemPermission::ViewOrders->value]->contains($this->selectedBranchId), 403);
            $this->waiterQueries->authorizeBranchView($this->currentUser(), $this->selectedBranchId);
            $this->branches = array_map(fn (array $branch): array => [
                ...$branch,
                'can_manage_settings' => $permissions[SystemPermission::ManageSettings->value]->contains((int) $branch['id']),
            ], $this->branches);
        }

        $attentionLabels = ['all' => __('operations.attention.all'), 'pending' => __('operations.attention.pending'), 'ready' => __('operations.attention.ready'), 'calls' => __('operations.attention.calls'), 'bills' => __('operations.attention.bills')];

        $availabilityBranch = $this->waiterQueries->branch($this->selectedBranchId);

        return view('livewire.waiter.dashboard', [
            'availabilityUrl' => route('organizations.brands.branches.availability.index', [$availabilityBranch->organization_id, $availabilityBranch->brand_id, $availabilityBranch->id]),
            'branchOverviewUrl' => route('restaurant.dashboard', ['branch' => $this->selectedBranchId]),
            'attentionOptions' => array_map(fn (string $type): array => ['value' => $type, 'label' => $attentionLabels[$type]], WaiterTableQueryService::ATTENTION_TYPES),
            'attentionReason' => $this->attention !== 'all' ? __('operations.attention.reason', ['type' => $attentionLabels[$this->attention]]) : null,
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

    private function validateSelections(): void
    {
        $this->selectedBranchId = $this->positiveIdentifier($this->selectedBranchId, true);
        $this->selectedTableSessionId = $this->positiveIdentifier($this->selectedTableSessionId, true);
        $this->tablePage = $this->positiveIdentifier($this->tablePage);
        abort_unless(is_string($this->attention) && in_array($this->attention, WaiterTableQueryService::ATTENTION_TYPES, true), 422);
        abort_unless(is_string($this->zoneScope) && in_array($this->zoneScope, ['mine', 'all'], true), 422);
    }

    private function positiveIdentifier(mixed $value, bool $nullable = false): ?int
    {
        if ($nullable && ($value === null || $value === '')) {
            return null;
        }
        abort_unless((is_int($value) && $value > 0) || (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1 && filter_var($value, FILTER_VALIDATE_INT) !== false), 422);

        return (int) $value;
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
