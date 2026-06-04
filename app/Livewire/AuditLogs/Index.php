<?php

namespace App\Livewire\AuditLogs;

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Audit log')]
class Index extends Component
{
    /**
     * @var array{has_access: bool, logs: list<array<string, mixed>>, branch_count: int}
     */
    public array $payload = [
        'has_access' => false,
        'logs' => [],
        'branch_count' => 0,
    ];

    public function mount(): void
    {
        $this->refreshAuditLog();
    }

    public function refreshAuditLog(): void
    {
        $this->payload = app(BuildAuditLogIndexAction::class)->handle($this->currentUser());

        if (! $this->payload['has_access']) {
            abort(403);
        }
    }

    public function render(): View
    {
        return view('livewire.audit-logs.index');
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
