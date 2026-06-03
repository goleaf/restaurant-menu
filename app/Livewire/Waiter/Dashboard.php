<?php

namespace App\Livewire\Waiter;

use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

    public ?int $previousNewDraftCount = null;

    public string $refreshedAt = '';

    public function mount(): void
    {
        $this->refreshDashboard();
        $this->previousNewDraftCount = $this->newDraftCount;
    }

    public function refreshDashboard(): void
    {
        $payload = app(BuildWaiterDashboardAction::class)->handle($this->currentUser());

        if (! $payload['has_access']) {
            abort(403);
        }

        $previousNewDraftCount = $this->previousNewDraftCount;

        $this->branches = $payload['branches'];
        $this->servicePointCount = $payload['service_point_count'];
        $this->activeSessionCount = $payload['active_session_count'];
        $this->newDraftCount = $payload['new_draft_count'];
        $this->refreshedAt = now()->format('H:i:s');

        if ($previousNewDraftCount !== null && $this->newDraftCount > $previousNewDraftCount) {
            $this->dispatch('waiter-new-draft');
        }

        $this->previousNewDraftCount = $this->newDraftCount;
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
}
