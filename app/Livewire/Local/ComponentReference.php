<?php

declare(strict_types=1);

namespace App\Livewire\Local;

use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class ComponentReference extends Component
{
    public mixed $name = '';

    public mixed $note = '';

    public function boot(Application $application): void
    {
        abort_unless($application->environment(['local', 'testing']), 404);
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperadmin(), 403);
    }

    public function mount(): void
    {
        $this->name = __('ui.reference.example_name');
    }

    public function saveExample(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:80'],
        ], ['name.required' => __('ui.reference.name_required')]);

        $this->modal('component-reference-edit')->close();
        Flux::toast(text: __('ui.reference.saved'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.local.component-reference')->title(__('ui.reference.title'));
    }
}
