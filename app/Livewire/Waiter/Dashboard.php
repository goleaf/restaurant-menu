<?php

namespace App\Livewire\Waiter;

use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\MarkWaiterCallHandledAction;
use App\Models\User;
use App\Models\WaiterCall;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Waiter dashboard')]
class Dashboard extends Component
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $branches = [];

    public int $servicePointCount = 0;

    public int $activeSessionCount = 0;

    public int $newDraftCount = 0;

    public int $waiterCallCount = 0;

    public ?int $previousNewDraftCount = null;

    public ?int $previousWaiterCallCount = null;

    public string $waiterCallMessage = '';

    public string $refreshedAt = '';

    public function mount(): void
    {
        $this->refreshDashboard();
        $this->previousNewDraftCount = $this->newDraftCount;
        $this->previousWaiterCallCount = $this->waiterCallCount;
    }

    public function refreshDashboard(): void
    {
        $payload = app(BuildWaiterDashboardAction::class)->handle($this->currentUser());

        if (! $payload['has_access']) {
            abort(403);
        }

        $previousNewDraftCount = $this->previousNewDraftCount;
        $previousWaiterCallCount = $this->previousWaiterCallCount;

        $this->branches = $payload['branches'];
        $this->servicePointCount = $payload['service_point_count'];
        $this->activeSessionCount = $payload['active_session_count'];
        $this->newDraftCount = $payload['new_draft_count'];
        $this->waiterCallCount = $payload['waiter_call_count'];
        $this->refreshedAt = now()->format('H:i:s');

        if ($previousNewDraftCount !== null && $this->newDraftCount > $previousNewDraftCount) {
            $this->dispatch('waiter-new-draft');
        }

        if ($previousWaiterCallCount !== null && $this->waiterCallCount > $previousWaiterCallCount) {
            $this->dispatch('waiter-called');
        }

        $this->previousNewDraftCount = $this->newDraftCount;
        $this->previousWaiterCallCount = $this->waiterCallCount;
    }

    public function markWaiterCallHandled(int $waiterCallId, MarkWaiterCallHandledAction $markHandled): void
    {
        $waiterCall = WaiterCall::query()
            ->select(['id'])
            ->whereKey($waiterCallId)
            ->firstOrFail();

        try {
            $markHandled->handle($waiterCall, $this->currentUser());
            $this->waiterCallMessage = __('Вызов официанта отмечен как обработанный.');
        } catch (ValidationException $exception) {
            $this->waiterCallMessage = $this->firstValidationMessage($exception);
        }

        $this->refreshDashboard();
    }

    public function render(): View
    {
        return view('livewire.waiter.dashboard');
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

        return (string) ($messages->first() ?? __('Не удалось обработать вызов официанта.'));
    }
}
