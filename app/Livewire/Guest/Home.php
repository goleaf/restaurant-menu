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
            'journeySteps' => [
                [
                    'title' => 'ui.pages.guest.home.permanent_qr',
                    'description' => 'ui.pages.guest.home.permanent_qr_description',
                ],
                [
                    'title' => 'ui.pages.guest.home.table_session',
                    'description' => 'ui.pages.guest.home.table_session_description',
                ],
                [
                    'title' => 'ui.pages.guest.home.shared_cart',
                    'description' => 'ui.pages.guest.home.shared_cart_description',
                ],
            ],
        ]);
    }
}
