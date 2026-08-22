<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class OfflineIndicator extends Component
{
    public function render(): View
    {
        return view('livewire.offline-indicator');
    }
}
