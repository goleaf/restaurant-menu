<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use Illuminate\View\View;
use Livewire\Component;

class Appearance extends Component
{
    public function render(): View
    {
        return view('livewire.settings.appearance')
            ->title(__('ui.settings.appearance.appearance_settings'));
    }
}
