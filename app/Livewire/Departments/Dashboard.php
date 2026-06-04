<?php

namespace App\Livewire\Departments;

use App\Actions\Departments\BuildDepartmentDashboardAction;
use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenTicketItem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

abstract class Dashboard extends Component
{
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

    public int $inProgressItemCount = 0;

    public int $readyItemCount = 0;

    public string $refreshedAt = '';

    public ?string $feedbackMessage = null;

    public string $pageTitle = '';

    public string $pageSubtitle = '';

    public string $dataPage = '';

    public string $emptyMessage = '';

    public string $itemCountLabel = '';

    /**
     * @var array<string, string>
     */
    public array $statusOptions = [];

    public function mount(): void
    {
        $this->statusOptions = KitchenTicketItemStatus::options();
        $this->pageTitle = $this->screenTitle();
        $this->pageSubtitle = $this->screenSubtitle();
        $this->dataPage = $this->screenDataPage();
        $this->emptyMessage = $this->screenEmptyMessage();
        $this->itemCountLabel = $this->screenItemCountLabel();
        $this->refreshDepartment();
    }

    public function updatedSelectedDepartmentId(): void
    {
        $this->refreshDepartment();
    }

    public function refreshDepartment(): void
    {
        $payload = app(BuildDepartmentDashboardAction::class)->handle(
            user: $this->currentUser(),
            selectedDepartmentId: $this->selectedDepartmentId === '' ? null : (int) $this->selectedDepartmentId,
            departmentTypes: $this->departmentTypes(),
            roleCodes: $this->roleCodes(),
            permissionCodes: $this->permissionCodes(),
        );

        if (! $payload['has_access']) {
            abort(403);
        }

        $this->departments = $payload['departments'];
        $this->tickets = $payload['tickets'];
        $this->selectedDepartmentId = $payload['selected_department_id'] === null ? '' : (string) $payload['selected_department_id'];
        $this->selectedDepartmentName = $payload['selected_department_name'];
        $this->ticketCount = $payload['ticket_count'];
        $this->newItemCount = $payload['new_item_count'];
        $this->inProgressItemCount = $payload['in_progress_item_count'];
        $this->readyItemCount = $payload['ready_item_count'];
        $this->refreshedAt = now()->format('H:i:s');
    }

    public function setItemStatus(int $itemId, string $status): void
    {
        $statusEnum = KitchenTicketItemStatus::tryFrom($status);

        if (! $statusEnum instanceof KitchenTicketItemStatus) {
            $this->addError('ticket_item_status', __('Неизвестный статус позиции.'));

            return;
        }

        try {
            $item = KitchenTicketItem::query()
                ->select(['id'])
                ->whereKey($itemId)
                ->firstOrFail();

            app(UpdateDepartmentTicketItemStatusAction::class)->handle(
                item: $item,
                status: $statusEnum,
                user: $this->currentUser(),
                departmentTypes: $this->departmentTypes(),
                roleCodes: $this->roleCodes(),
                permissionCodes: $this->permissionCodes(),
            );
            $this->feedbackMessage = __('Status updated.');
            $this->resetErrorBag('ticket_item_status');
            $this->refreshDepartment();
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    public function render(): View
    {
        return view('livewire.departments.dashboard');
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

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
