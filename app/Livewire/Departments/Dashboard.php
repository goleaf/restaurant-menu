<?php

declare(strict_types=1);

namespace App\Livewire\Departments;

use App\Actions\Departments\BuildDepartmentDashboardAction;
use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Enums\DepartmentTicketFilter;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

abstract class Dashboard extends Component
{
    private const int TICKETS_PER_PAGE = 24;

    private BuildDepartmentDashboardAction $buildDepartmentDashboard;

    private UpdateDepartmentTicketItemStatusAction $updateDepartmentTicketItemStatus;

    /**
     * @var list<array<string, mixed>>
     */
    public array $departments = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $tickets = [];

    public string $selectedDepartmentId = '';

    public ?string $selectedDepartmentName = null;

    public int $ticketCount = 0;

    public int $newItemCount = 0;

    public int $acceptedItemCount = 0;

    public int $inProgressItemCount = 0;

    public int $readyItemCount = 0;

    public int $completedItemCount = 0;

    public int $cancelledItemCount = 0;

    public string $ticketFilter = 'active';

    /**
     * @var array<string, string>
     */
    public array $ticketFilterOptions = [];

    public int $ticketPage = 1;

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

    public function boot(
        BuildDepartmentDashboardAction $buildDepartmentDashboard,
        UpdateDepartmentTicketItemStatusAction $updateDepartmentTicketItemStatus,
    ): void {
        $this->buildDepartmentDashboard = $buildDepartmentDashboard;
        $this->updateDepartmentTicketItemStatus = $updateDepartmentTicketItemStatus;
    }

    public function mount(): void
    {
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
        $this->resetTicketPageAndFingerprint();
        $this->refreshDepartment();
    }

    public function updatedTicketFilter(string $filter): void
    {
        $this->ticketFilter = (DepartmentTicketFilter::tryFrom($filter) ?? DepartmentTicketFilter::Active)->value;
        $this->resetTicketPageAndFingerprint();
        $this->refreshDepartment();
    }

    public function refreshDepartment(): void
    {
        $filter = DepartmentTicketFilter::tryFrom($this->ticketFilter) ?? DepartmentTicketFilter::Active;
        $this->ticketFilter = $filter->value;
        $payload = $this->departmentPayload($filter);

        if ($payload['tickets'] === [] && $this->ticketPage > 1 && $payload['ticket_count'] > 0) {
            $this->ticketPage = max(1, (int) ceil($payload['ticket_count'] / self::TICKETS_PER_PAGE));
            $payload = $this->departmentPayload($filter);
        }

        if (! $payload['has_access']) {
            abort(403);
        }

        $this->departments = $payload['departments'];
        $this->tickets = $payload['tickets'];
        $this->selectedDepartmentId = $payload['selected_department_id'] === null ? '' : (string) $payload['selected_department_id'];
        $this->selectedDepartmentName = $payload['selected_department_name'];
        $this->ticketCount = $payload['ticket_count'];
        $this->newItemCount = $payload['new_item_count'];
        $this->acceptedItemCount = $payload['accepted_item_count'];
        $this->inProgressItemCount = $payload['in_progress_item_count'];
        $this->readyItemCount = $payload['ready_item_count'];
        $this->completedItemCount = $payload['completed_item_count'];
        $this->cancelledItemCount = $payload['cancelled_item_count'];
        $this->ticketPage = $payload['ticket_page'];
        $this->hasPreviousTicketPage = $payload['has_previous_ticket_page'];
        $this->hasNextTicketPage = $payload['has_next_ticket_page'];
        $this->sortLabel = $payload['sort_label'];
        $this->refreshedAt = LocalizedDateFormatter::timeWithSeconds(now()) ?? '';
        $this->announceQueueChanges();
    }

    public function previousTicketPage(): void
    {
        if ($this->ticketPage <= 1) {
            return;
        }

        $this->ticketPage--;
        $this->queueFingerprint = '';
        $this->refreshDepartment();
    }

    public function nextTicketPage(): void
    {
        if (! $this->hasNextTicketPage) {
            return;
        }

        $this->ticketPage++;
        $this->queueFingerprint = '';
        $this->refreshDepartment();
    }

    /**
     * @return array<string, mixed>
     */
    private function departmentPayload(DepartmentTicketFilter $filter): array
    {
        return $this->buildDepartmentDashboard->handle(
            user: $this->currentUser(),
            selectedDepartmentId: $this->selectedDepartmentId === '' ? null : (int) $this->selectedDepartmentId,
            departmentTypes: $this->departmentTypes(),
            roleCodes: $this->roleCodes(),
            permissionCodes: $this->permissionCodes(),
            filter: $filter,
            page: $this->ticketPage,
            perPage: self::TICKETS_PER_PAGE,
        );
    }

    public function setItemStatus(int $itemId, string $status): void
    {
        $this->feedbackMessage = null;
        $this->resetErrorBag('ticket_item_status');

        $statusEnum = KitchenTicketItemStatus::tryFrom($status);

        if (! $statusEnum instanceof KitchenTicketItemStatus) {
            $this->addError('ticket_item_status', __('ui.livewire.departments.dashboard.neizvestnyi_status_pozicii'));

            return;
        }

        try {
            $this->updateDepartmentTicketItemStatus->handle(
                itemId: $itemId,
                status: $statusEnum,
                user: $this->currentUser(),
                departmentTypes: $this->departmentTypes(),
                roleCodes: $this->roleCodes(),
                permissionCodes: $this->permissionCodes(),
            );
            $this->feedbackMessage = __('ui.livewire.departments.dashboard.status_updated');
            $this->resetErrorBag('ticket_item_status');
            $this->refreshDepartment();
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    public function render(): View
    {
        return view('livewire.departments.dashboard')->title($this->screenTitle());
    }

    /**
     * @return list<KitchenDepartmentType>
     */
    abstract protected function departmentTypes(): array;

    /**
     * @return list<SystemRole>
     */
    abstract protected function roleCodes(): array;

    /**
     * @return list<SystemPermission>
     */
    abstract protected function permissionCodes(): array;

    abstract protected function screenTitle(): string;

    abstract protected function screenSubtitle(): string;

    abstract protected function screenDataPage(): string;

    abstract protected function screenEmptyMessage(): string;

    abstract protected function screenItemCountLabel(): string;

    private function resetTicketPageAndFingerprint(): void
    {
        $this->ticketPage = 1;
        $this->queueFingerprint = '';
        $this->updateAnnouncement = null;
    }

    private function announceQueueChanges(): void
    {
        $queueState = collect($this->tickets)
            ->map(fn (array $ticket): array => [
                'id' => $ticket['id'],
                'items' => collect($ticket['items'])
                    ->map(fn (array $item): array => [
                        'id' => $item['id'],
                        'status' => $item['status_value'],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
        $fingerprint = hash('sha256', json_encode($queueState, JSON_THROW_ON_ERROR));

        if ($this->queueFingerprint !== '' && $this->queueFingerprint !== $fingerprint) {
            $this->updateAnnouncement = __('ui.departments.dashboard.queue_updated', [
                'time' => $this->refreshedAt,
            ]);
        }

        $this->queueFingerprint = $fingerprint;
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
