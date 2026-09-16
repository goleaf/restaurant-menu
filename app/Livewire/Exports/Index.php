<?php

declare(strict_types=1);

namespace App\Livewire\Exports;

use App\Actions\Exports\BuildDataExportsIndexAction;
use App\Models\User;
use App\Services\Navigation\WorkspaceContextResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public ?int $branchId = null;

    public function mount(WorkspaceContextResolver $resolver): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->branchId = $resolver->resolve($user, request(), pageDestination: 'reports')->branchId;
    }

    public function render(BuildDataExportsIndexAction $build): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $exports = $build->handle($user, $this->branchId);
        abort_unless($exports['has_access'], 403);

        return view('livewire.exports.index', ['exports' => $exports])->title(__('reports.exports.title'));
    }
}
