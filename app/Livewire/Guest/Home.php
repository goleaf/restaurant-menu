<?php

declare(strict_types=1);

namespace App\Livewire\Guest;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Home extends Component
{
    public function render(): View
    {
        return view('livewire.guest.home', [
            'featureKeys' => [
                'ui.pages.guest.home.permanent_qr',
                'ui.pages.guest.home.table_session',
                'ui.pages.guest.home.shared_cart',
            ],
        ]);
    }
}
