<?php

namespace App\Livewire\AuditLogs;

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Audit log')]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        if (! app(BuildAuditLogIndexAction::class)->userHasAccess($this->currentUser())) {
            abort(403);
        }
    }

    public function refreshAuditLog(): void
    {
        unset($this->payload);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function payload(): array
    {
        $payload = app(BuildAuditLogIndexAction::class)->handle($this->currentUser());

        if (! $payload['has_access']) {
            abort(403);
        }

        return $payload;
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
