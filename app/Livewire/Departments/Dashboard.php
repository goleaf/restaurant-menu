<?php

declare(strict_types=1);

namespace App\Livewire\Departments;

use App\Actions\Departments\BuildDepartmentDashboardAction;
use App\Actions\Departments\ResolveAccessibleDepartmentIdsAction;
use App\Actions\Departments\ResolvePreparationAccessibleDepartmentIdsAction;
use App\Actions\Departments\ResolvePreparationTicketContextAction;
use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\Departments\UpdatePreparationTicketItemsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\DepartmentTicketFilter;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Forms\Departments\PreparationFilterForm;
use App\Livewire\Forms\Departments\PreparationSelectionForm;
use App\Models\User;
use App\Services\Navigation\WorkspaceContextResolver;
use App\Support\LocalizedDateFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    #[Locked]
    public ?int $branchId = null;

    #[Locked]
    public string $branchName = '';

    public PreparationFilterForm $filters;

    public PreparationSelectionForm $selection;

    private const int TICKETS_PER_PAGE = 24;

    private BuildDepartmentDashboardAction $buildDepartmentDashboard;

    private UpdateDepartmentTicketItemStatusAction $updateDepartmentTicketItemStatus;

    private ResolvePreparationAccessibleDepartmentIdsAction $resolveDepartments;

    /** @var list<int>|null */
    private ?array $authorizedDepartmentIds = null;

    private bool $queueRefreshed = false;

    /** @var list<int> */
    #[Locked]
    public array $loadedDepartmentIds = [];

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $routingIssues = [];

    public int $routingIssueCount = 0;

    public int $recentCancelledItemCount = 0;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $departments = [];

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $tickets = [];

    #[Url(as: 'department', history: true)]
    public mixed $selectedDepartmentId = '';

    #[Url(as: 'ticket', history: true)]
    public mixed $selectedTicketId = '';

    #[Url(as: 'filter', history: true)]
    public mixed $ticketFilter = 'active';

    #[Url(as: 'page', history: true)]
    public mixed $ticketPage = 1;

    #[Url(as: 'items', history: true)]
    public mixed $itemPage = 1;

    #[Url(as: 'compact', history: true)]
    public bool $compact = false;

    public ?string $selectedDepartmentName = null;

    public int $ticketCount = 0;

    public int $newItemCount = 0;

    public int $acceptedItemCount = 0;

    public int $inProgressItemCount = 0;

    public int $readyItemCount = 0;

    public int $completedItemCount = 0;

    public int $cancelledItemCount = 0;

    public int $portionCount = 0;

    /** @var array<string, string> */
    public array $ticketFilterOptions = [];

    public bool $hasPreviousTicketPage = false;

    public bool $hasNextTicketPage = false;

    public string $sortLabel = '';

    public string $refreshedAt = '';

    public ?string $feedbackMessage = null;

    public ?string $updateAnnouncement = null;

    public string $pageTitle = '';

    public string $pageSubtitle = '';

    public string $dataPage = '';

    public string $emptyMessage = '';

    public string $itemCountLabel = '';

    #[Locked]
    public string $queueFingerprint = '';

    /** @var array<int, string> */
    public array $itemFeedback = [];

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $reviewItems = [];

    #[Locked]
    public string $reviewStatus = '';

    #[Locked]
    public ?int $reviewTicketId = null;

    /** @var list<array{id: int, successful: bool, message: string, notification_pending: bool}> */
    #[Locked]
    public array $batchResults = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $pendingNotifications = [];

    public function boot(BuildDepartmentDashboardAction $buildDepartmentDashboard, UpdateDepartmentTicketItemStatusAction $updateDepartmentTicketItemStatus, ResolvePreparationAccessibleDepartmentIdsAction $resolveDepartments): void
    {
        $this->buildDepartmentDashboard = $buildDepartmentDashboard;
        $this->updateDepartmentTicketItemStatus = $updateDepartmentTicketItemStatus;
        $this->resolveDepartments = $resolveDepartments;
        $this->authorizedDepartmentIds = null;
    }

    public function mount(WorkspaceContextResolver $resolver): void
    {
        $context = $resolver->resolve($this->currentUser(), request(), pageDestination: $this->workspaceDestination());
        $this->branchId = $context->branchId;
        $this->branchName = $context->name ?? '';
        if ($this->branchId === null) {
            $this->redirectRoute('dashboard');

            return;
        }
        $this->ticketFilterOptions = DepartmentTicketFilter::options();
        $this->pageTitle = $this->screenTitle();
        $this->pageSubtitle = $this->screenSubtitle();
        $this->dataPage = $this->screenDataPage();
        $this->emptyMessage = $this->screenEmptyMessage();
        $this->itemCountLabel = $this->screenItemCountLabel();
        $this->refreshDepartment();
    }

    public function updatedSelectedDepartmentId(): void
    {
        $this->clearSelection();
        $this->selectedTicketId = '';
        $this->resetTicketPageAndFingerprint();
        $this->refreshDepartment();
    }

    public function updatedTicketFilter(): void
    {
        $this->clearSelection();
        $this->selectedTicketId = '';
        $this->resetTicketPageAndFingerprint();
        $this->refreshDepartment();
    }

    public function updatedSelectedTicketId(): void
    {
        $this->clearSelection();
        $this->itemPage = 1;
        $this->refreshDepartment();
    }

    public function updatedTicketPage(): void
    {
        $this->clearSelection();
        $this->refreshDepartment();
    }

    public function updatedItemPage(): void
    {
        $this->clearSelection();
        $this->refreshDepartment();
    }

    public function refreshDepartment(): void
    {
        $data = $this->validatedContext();
        $ids = $this->authorizedDepartments();
        abort_if($ids === [], 403);
        if ($data['department'] !== '' && $data['department'] !== 'all') {
            if (! in_array((int) $data['department'], $ids, true)) {
                abort(403);
            }
        }
        if ($this->selection->itemIds !== [] || $this->reviewItems !== []) {
            foreach ($this->tickets as $ticket) {
                abort_unless(in_array($ticket['department_id'], $ids, true), 403);
            }

            return;
        }
        if ($data['ticket'] !== null) {
            $ticketContext = app(ResolvePreparationTicketContextAction::class)->handle($this->currentUser(), $data['ticket'], $this->branchId, $ids);
            abort_if($data['department'] !== '' && $data['department'] !== 'all' && (int) $data['department'] !== $ticketContext['department_id'], 409);
            if ($data['department'] === '') {
                $data['department'] = (string) $ticketContext['department_id'];
            }
        }
        $payload = $this->departmentPayload($data, $ids);
        if ($payload['tickets'] === [] && $data['page'] > 1 && $payload['ticket_count'] > 0) {
            $data['page'] = max(1, (int) ceil($payload['ticket_count'] / self::TICKETS_PER_PAGE));
            $payload = $this->departmentPayload($data, $ids);
        }
        abort_unless($payload['has_access'], 403);
        $this->loadedDepartmentIds = $ids;
        $this->queueRefreshed = true;
        $this->departments = $payload['departments'];
        $this->tickets = $payload['tickets'];
        $this->routingIssues = $payload['routing_issues'] ?? [];
        $this->routingIssueCount = $payload['routing_issue_count'] ?? 0;
        $this->recentCancelledItemCount = $payload['recent_cancelled_item_count'] ?? 0;
        $this->pendingNotifications = [];
        foreach ($this->tickets as $ticket) {
            foreach ($ticket['items'] as $item) {
                if ($item['can_retry_notification'] ?? false) {
                    $this->pendingNotifications[$item['id']] = $this->pendingNotificationContext(
                        [...$item, 'ticket_id' => $ticket['id'], 'department_id' => $ticket['department_id']], $item['status_value'],
                    );
                }
            }
        }
        $this->selectedDepartmentId = $data['department'] === 'all' ? 'all' : (string) ($payload['selected_department_id'] ?? '');
        $this->selectedDepartmentName = $data['department'] === 'all' ? __('preparation.all_departments') : $payload['selected_department_name'];
        $this->ticketCount = $payload['ticket_count'];
        $this->newItemCount = $payload['new_item_count'];
        $this->acceptedItemCount = $payload['accepted_item_count'];
        $this->inProgressItemCount = $payload['in_progress_item_count'];
        $this->readyItemCount = $payload['ready_item_count'];
        $this->completedItemCount = $payload['completed_item_count'];
        $this->cancelledItemCount = $payload['cancelled_item_count'];
        $this->portionCount = $payload['portion_count'] ?? 0;
        $this->ticketPage = $payload['ticket_page'];
        $this->hasPreviousTicketPage = $payload['has_previous_ticket_page'];
        $this->hasNextTicketPage = $payload['has_next_ticket_page'];
        $this->sortLabel = $payload['sort_label'];
        $this->refreshedAt = LocalizedDateFormatter::timeWithSeconds(now($payload['branch_timezone'] ?? config('app.timezone'))) ?? '';
        $this->resetErrorBag(['filters.department', 'filters.filter', 'filters.ticket', 'filters.page', 'filters.itemPage']);
        $this->announceQueueChanges();
        $this->dispatch('preparation-refreshed');
    }

    public function refreshQueue(): void
    {
        $this->clearSelection();
        $this->refreshDepartment();
    }

    public function openTicket(int $ticketId): void
    {
        $this->clearSelection();
        $this->selectedTicketId = $ticketId;
        $this->itemPage = 1;
        $this->refreshDepartment();
    }

    public function closeTicket(): void
    {
        $this->clearSelection();
        $this->selectedTicketId = '';
        $this->itemPage = 1;
        $this->refreshDepartment();
    }

    public function previousTicketPage(): void
    {
        $data = $this->validatedContext();
        $this->clearSelection();
        $this->ticketPage = max(1, $data['page'] - 1);
        $this->queueFingerprint = '';
        $this->refreshDepartment();
    }

    public function nextTicketPage(): void
    {
        $data = $this->validatedContext();
        if (! $this->hasNextTicketPage) {
            return;
        }
        $this->clearSelection();
        $this->ticketPage = $data['page'] + 1;
        $this->queueFingerprint = '';
        $this->refreshDepartment();
    }

    /** @param array{department: string, filter: string, ticket: ?int, page: int, itemPage: int} $data @param list<int> $ids @return array<string, mixed> */
    private function departmentPayload(array $data, array $ids): array
    {
        return $this->buildDepartmentDashboard->handle(
            user: $this->currentUser(),
            selectedDepartmentId: in_array($data['department'], ['', 'all'], true) ? null : (int) $data['department'],
            departmentTypes: $this->departmentTypes(), roleCodes: $this->roleCodes(), permissionCodes: $this->permissionCodes(),
            filter: DepartmentTicketFilter::from($data['filter']), page: $data['page'], perPage: self::TICKETS_PER_PAGE,
            branchId: $this->branchId, accessibleDepartmentIds: $ids, overview: $data['department'] === 'all', selectedTicketId: $data['ticket'], itemPage: $data['itemPage'],
        );
    }

    public function setItemStatus(int $itemId, string $status, ?string $expectedStatus = null, ?string $expectedUpdatedAt = null, ?int $ticketId = null): void
    {
        $this->feedbackMessage = null;
        $this->resetErrorBag('ticket_item_status');
        $this->validatedContext();
        try {
            $shown = $this->shownItem($itemId);
            $this->assertShownContext($shown);
            $next = KitchenTicketItemStatus::tryFrom($status);
            if ($next === null) {
                throw ValidationException::withMessages(['ticket_item_status' => __('ui.livewire.departments.dashboard.neizvestnyi_status_pozicii')]);
            }
            $expected = KitchenTicketItemStatus::tryFrom($expectedStatus ?? $shown['status_value']);
            if ($expected === null || $expected->value !== $shown['status_value'] || ($expectedUpdatedAt !== null && $expectedUpdatedAt !== $shown['version']) || ($ticketId !== null && $ticketId !== $shown['ticket_id'])) {
                throw ValidationException::withMessages(['ticket_item_status' => __('preparation.errors.stale_item')]);
            }
            $item = $this->updateDepartmentTicketItemStatus->handlePreparation($itemId, $next, $this->currentUser(), $this->branchId, $expected, $expectedUpdatedAt ?? $shown['version'], $shown['ticket_id']);
            $pending = (bool) $item->getAttribute('notification_delivery_pending');
            $this->itemFeedback[$itemId] = $pending ? __('preparation.notifications.pending') : __('ui.livewire.departments.dashboard.status_updated');
            if ($pending) {
                $this->pendingNotifications[$itemId] = $this->pendingNotificationContext($shown, $status);
            } else {
                unset($this->pendingNotifications[$itemId]);
            }
            $this->feedbackMessage = __('preparation.updated_item', ['id' => $itemId, 'result' => $this->itemFeedback[$itemId]]);
            $this->clearSelection();
            $this->refreshDepartment();
            $this->dispatch('preparation-action-finished', itemId: $itemId);
        } catch (ValidationException $exception) {
            $this->itemFeedback[$itemId] = (string) collect($exception->errors())->flatten()->first();
            throw $exception;
        }
    }

    public function retryNotification(int $itemId): void
    {
        $shown = $this->pendingNotifications[$itemId] ?? null;
        abort_if($shown === null, 404);
        $this->assertShownContext($shown);
        $target = KitchenTicketItemStatus::tryFrom($shown['target_status']);
        $expected = KitchenTicketItemStatus::tryFrom($shown['status_value']);
        if ($target === null || $expected === null) {
            throw ValidationException::withMessages(['ticket_item_status' => __('preparation.errors.stale_item')]);
        }
        $item = $this->updateDepartmentTicketItemStatus->handlePreparation($itemId, $target, $this->currentUser(), $this->branchId, $expected, $shown['version'], $shown['ticket_id']);
        if (! $item->getAttribute('notification_delivery_pending')) {
            unset($this->pendingNotifications[$itemId]);
            $this->itemFeedback[$itemId] = __('ui.livewire.departments.dashboard.status_updated');
        }
        $this->refreshDepartment();
    }

    public function reviewSelection(): void
    {
        $this->validatedContext();
        $data = $this->selection->validated();
        $rows = [];
        $ticketId = null;
        $next = KitchenTicketItemStatus::from($data['status']);
        foreach ($data['itemIds'] as $id) {
            $shown = $this->shownItem((int) $id);
            $this->assertShownContext($shown);
            $status = KitchenTicketItemStatus::tryFrom($shown['status_value']);
            if (($ticketId !== null && $ticketId !== $shown['ticket_id']) || $status === null || ! in_array($next, $status->productionTransitions(), true)) {
                throw ValidationException::withMessages(['selection.itemIds' => __('preparation.selection.invalid')]);
            }
            $ticketId = $shown['ticket_id'];
            $rows[] = $shown;
        }
        $this->reviewItems = $rows;
        $this->reviewStatus = $data['status'];
        $this->reviewTicketId = $ticketId;
        $this->batchResults = [];
        $this->modal('preparation-review')->show();
    }

    public function applySelection(UpdatePreparationTicketItemsAction $action): void
    {
        $this->validatedContext();
        abort_if($this->reviewTicketId === null || $this->reviewItems === [], 422);
        foreach ($this->reviewItems as $shown) {
            $this->assertShownContext($shown);
        }
        $items = array_map(fn (array $item): array => ['id' => $item['id'], 'status' => $item['status_value'], 'updated_at' => $item['version']], $this->reviewItems);
        $this->batchResults = $action->handle($this->currentUser(), $this->branchId, $this->reviewTicketId, KitchenTicketItemStatus::from($this->reviewStatus), $items);
        $this->clearSelection();
        $this->modal('preparation-review')->close();
        $this->refreshDepartment();
    }

    public function clearSelection(): void
    {
        $this->selection->reset();
        $this->reviewItems = [];
        $this->reviewStatus = '';
        $this->reviewTicketId = null;
    }

    public function setView(string $view): void
    {
        abort_unless(in_array($view, ['active', 'ready', 'history'], true), 422);
        $this->ticketFilter = $view;
        $this->updatedTicketFilter();
    }

    /** @param array<string, mixed> $shown */
    private function assertShownContext(array $shown): void
    {
        $data = $this->validatedContext();
        if (! in_array($shown['department_id'], $this->authorizedDepartments(), true)
            || ($data['department'] !== 'all' && (int) $data['department'] !== $shown['department_id'])
            || ($data['ticket'] !== null && $data['ticket'] !== $shown['ticket_id'])) {
            throw ValidationException::withMessages(['ticket_item_status' => __('preparation.errors.stale_item')]);
        }
    }

    public function render(): View
    {
        if ($this->branchId !== null) {
            $ids = $this->authorizedDepartments();
            abort_if($ids === [], 403);
            abort_if(array_diff($this->loadedDepartmentIds, $ids) !== [], 403);
            if ((is_string($this->selectedDepartmentId) || is_int($this->selectedDepartmentId)) && ctype_digit((string) $this->selectedDepartmentId) && (int) $this->selectedDepartmentId > 0) {
                abort_unless(in_array((int) $this->selectedDepartmentId, $ids, true), 403);
            }
            foreach ($this->tickets as $ticket) {
                abort_unless(in_array($ticket['department_id'], $ids, true), 403);
            }
            $this->pendingNotifications = array_filter($this->pendingNotifications, fn (array $item): bool => in_array($item['department_id'], $ids, true));
            if (! $this->queueRefreshed && $this->routingIssueCount > 0
                && ! app(ResolveWaiterAccessibleBranchIdsAction::class)->handle($this->currentUser(), SystemPermission::ManageMenu)->contains($this->branchId)) {
                $this->routingIssues = [];
                $this->routingIssueCount = 0;
            }
        }
        $filter = is_string($this->ticketFilter) ? DepartmentTicketFilter::tryFrom($this->ticketFilter) : null;
        $hasSelectedTicket = (is_string($this->selectedTicketId) || is_int($this->selectedTicketId))
            && ctype_digit((string) $this->selectedTicketId) && (int) $this->selectedTicketId > 0;

        return view('livewire.departments.dashboard', [
            'ticketCountLabel' => $hasSelectedTicket ? __('preparation.selected_ticket', ['number' => $this->selectedTicketId]) : __('preparation.tickets_count', ['count' => $this->ticketCount]),
            'portionCountLabel' => __($hasSelectedTicket ? 'preparation.ticket_portions' : 'preparation.portions_count', ['count' => $this->portionCount]),
            'activeFilterLabel' => ($filter ?? DepartmentTicketFilter::Active)->label(),
            'activeView' => $filter?->isHistory() ? 'history' : ($filter === DepartmentTicketFilter::Ready ? 'ready' : 'active'),
            'reviewPortionCount' => array_sum(array_column($this->reviewItems, 'quantity')),
            'reviewRowCount' => count($this->reviewItems),
            'reviewStatusLabel' => KitchenTicketItemStatus::tryFrom($this->reviewStatus)?->label() ?? '',
            'selectionCount' => count($this->selection->itemIds),
        ])->title($this->screenTitle());
    }

    /** @return array{department: string, filter: string, ticket: ?int, page: int, itemPage: int} */
    private function validatedContext(): array
    {
        return $this->filters->validated($this->selectedDepartmentId, $this->ticketFilter, $this->selectedTicketId, $this->ticketPage, $this->itemPage);
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function pendingNotificationContext(array $item, string $target): array
    {
        return [
            'ticket_id' => $item['ticket_id'], 'department_id' => $item['department_id'],
            'status_value' => $item['status_value'], 'version' => $item['version'], 'target_status' => $target,
        ];
    }

    /** @return list<int> */
    private function authorizedDepartments(): array
    {
        return $this->authorizedDepartmentIds ??= $this->workspaceDestination() === 'preparation'
            ? $this->resolveDepartments->handle($this->currentUser(), $this->branchId)->all()
            : app(ResolveAccessibleDepartmentIdsAction::class)->handle($this->currentUser(), $this->departmentTypes(), $this->roleCodes(), $this->permissionCodes(), branchId: $this->branchId)->all();
    }

    /** @return array<string, mixed> */
    private function shownItem(int $id): array
    {
        foreach ($this->tickets as $ticket) {
            foreach ($ticket['items'] as $item) {
                if ($item['id'] === $id) {
                    return [...$item, 'ticket_id' => $ticket['id'], 'department_id' => $ticket['department_id'], 'department_name' => $ticket['department_name']];
                }
            }
        }
        throw ValidationException::withMessages(['ticket_item_status' => __('preparation.errors.not_visible')]);
    }

    /** @return list<KitchenDepartmentType> */
    protected function departmentTypes(): array
    {
        return KitchenDepartmentType::cases();
    }

    /** @return list<SystemRole> */
    protected function roleCodes(): array
    {
        return [];
    }

    /** @return list<SystemPermission> */
    protected function permissionCodes(): array
    {
        return [];
    }

    protected function workspaceDestination(): string
    {
        return 'preparation';
    }

    protected function screenTitle(): string
    {
        return __('preparation.title');
    }

    protected function screenSubtitle(): string
    {
        return __('preparation.subtitle');
    }

    protected function screenDataPage(): string
    {
        return 'preparation-dashboard';
    }

    protected function screenEmptyMessage(): string
    {
        return __('departments.dashboard.no_orders');
    }

    protected function screenItemCountLabel(): string
    {
        return __('preparation.rows');
    }

    private function resetTicketPageAndFingerprint(): void
    {
        $this->ticketPage = 1;
        $this->itemPage = 1;
        $this->queueFingerprint = '';
        $this->updateAnnouncement = null;
    }

    private function announceQueueChanges(): void
    {
        $state = array_map(fn (array $ticket): array => [
            'id' => $ticket['id'], 'department' => $ticket['department_id'],
            'work_status' => $ticket['work_status'], 'place' => $ticket['service_point_label'],
            'items' => array_map(fn (array $item): array => [$item['id'], $item['status_value'], $item['completed_at'], $item['version']], $ticket['items']),
        ], $this->tickets);
        $fingerprint = hash('sha256', json_encode([$state, $this->recentCancelledItemCount], JSON_THROW_ON_ERROR));
        if ($this->queueFingerprint !== '' && $this->queueFingerprint !== $fingerprint) {
            $this->updateAnnouncement = __('ui.departments.dashboard.queue_updated', ['time' => $this->refreshedAt]);
        }
        $this->queueFingerprint = $fingerprint;
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
