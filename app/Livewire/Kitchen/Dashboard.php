<?php

namespace App\Livewire\Kitchen;

use App\Actions\Kitchen\BuildKitchenDashboardAction;
use App\Actions\Kitchen\UpdateKitchenTicketItemStatusAction;
use App\Enums\KitchenTicketItemStatus;
use App\Models\KitchenTicketItem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kitchen screen')]
class Dashboard extends Component
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

    /**
     * @var array<string, string>
     */
    public array $statusOptions = [];

    public function mount(): void
    {
        $this->statusOptions = KitchenTicketItemStatus::options();
        $this->refreshKitchen();
    }

    public function updatedSelectedDepartmentId(): void
    {
        $this->refreshKitchen();
    }

    public function refreshKitchen(): void
    {
        $payload = app(BuildKitchenDashboardAction::class)->handle(
            $this->currentUser(),
            $this->selectedDepartmentId === '' ? null : (int) $this->selectedDepartmentId,
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

            app(UpdateKitchenTicketItemStatusAction::class)->handle($item, $statusEnum, $this->currentUser());
            $this->feedbackMessage = __('Status updated.');
            $this->resetErrorBag('ticket_item_status');
            $this->refreshKitchen();
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    public function render(): View
    {
        return view('livewire.kitchen.dashboard');
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
