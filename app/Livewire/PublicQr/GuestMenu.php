<?php

namespace App\Livewire\PublicQr;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use Illuminate\View\View;
use Livewire\Component;

class GuestMenu extends Component
{
    public int $branchId;

    public string $currency = 'EUR';

    public function mount(int $branchId, string $currency = 'EUR'): void
    {
        $this->branchId = $branchId;
        $this->currency = $currency;
    }

    public function render(): View
    {
        return view('livewire.public-qr.guest-menu', [
            'guestMenu' => app(GetGuestMenuForBranchAction::class)->handle($this->branchId),
        ]);
    }
}
