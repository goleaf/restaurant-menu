<?php

declare(strict_types=1);

namespace App\Livewire\Waiter;

use App\Actions\Waiter\BuildWaiterSessionHistoryAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
final class TableSessionHistory extends Component
{
    /** @var list<array<string, mixed>> */
    public array $sessions = [];

    public function mount(BuildWaiterSessionHistoryAction $buildHistory): void
    {
        $this->refreshHistory($buildHistory);
    }

    public function refreshHistory(BuildWaiterSessionHistoryAction $buildHistory): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        $payload = $buildHistory->handle($user);

        if (! $payload['has_access']) {
            abort(403);
        }

        $this->sessions = $payload['sessions'];
    }

    public function render(): View
    {
        return view('livewire.waiter.table-session-history');
    }
}
