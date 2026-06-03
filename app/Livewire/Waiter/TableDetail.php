<?php

namespace App\Livewire\Waiter;

use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Table detail')]
class TableDetail extends Component
{
    public int $tableSessionId;

    /**
     * @var array<string, mixed>
     */
    public array $table = [];

    public string $refreshedAt = '';

    public function mount(TableSession $tableSession): void
    {
        $this->tableSessionId = $tableSession->id;
        $this->refreshTable();
    }

    public function refreshTable(): void
    {
        $tableSession = TableSession::query()
            ->select(['id', 'branch_id'])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();

        $payload = app(BuildWaiterTableDetailAction::class)->handle($this->currentUser(), $tableSession);

        if (! $payload['has_access']) {
            abort(403);
        }

        $this->table = $payload['table'] ?? [];
        $this->refreshedAt = now()->format('H:i:s');
    }

    public function render(): View
    {
        return view('livewire.waiter.table-detail');
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
