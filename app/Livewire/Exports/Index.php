<?php

namespace App\Livewire\Exports;

use App\Actions\Exports\BuildDataExportsIndexAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    /**
     * @var array<string, mixed>
     */
    public array $exports = [
        'has_access' => false,
        'branches' => [],
        'export_types' => [],
    ];

    public function mount(BuildDataExportsIndexAction $buildDataExportsIndex): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $this->exports = $buildDataExportsIndex->handle($user);

        abort_unless((bool) $this->exports['has_access'], 403);
    }

    public function render(): View
    {
        return view('livewire.exports.index')
            ->title(__('reports.exports.title'));
    }
}
