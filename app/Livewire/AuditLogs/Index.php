<?php

declare(strict_types=1);

namespace App\Livewire\AuditLogs;

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Navigation\WorkspaceContextResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $branchId = null;

    private BuildAuditLogIndexAction $buildAuditLogIndex;

    public function boot(BuildAuditLogIndexAction $buildAuditLogIndex): void
    {
        $this->buildAuditLogIndex = $buildAuditLogIndex;
    }

    public function mount(WorkspaceContextResolver $resolver): void
    {
        Gate::forUser($this->currentUser())->authorize('viewAny', AuditLog::class);
        $this->branchId = $resolver->resolve($this->currentUser(), request(), pageDestination: 'audit')->branchId;
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
        $payload = $this->buildAuditLogIndex->handle($this->currentUser(), branchId: $this->branchId);

        if (! $payload['has_access']) {
            abort(403);
        }

        return $payload;
    }

    public function render(): View
    {
        return view('livewire.audit-logs.index', [
            'payload' => $this->payload(),
        ])->title(__('navigation.audit_log'));
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
