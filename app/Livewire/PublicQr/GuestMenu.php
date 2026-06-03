<?php

namespace App\Livewire\PublicQr;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class GuestMenu extends Component
{
    public int $branchId;

    public string $currency = 'EUR';

    #[Url(as: 'lang')]
    public string $language = '';

    /**
     * @var array<string, string>
     */
    public array $languageOptions = [];

    public function mount(int $branchId, string $currency = 'EUR'): void
    {
        $this->branchId = $branchId;
        $this->currency = $currency;
        $this->languageOptions = GetGuestMenuForBranchAction::supportedLanguageLabels();
        $this->language = app(GetGuestMenuForBranchAction::class)->resolveLanguageForBranch($branchId, $this->language);
    }

    public function updatedLanguage(): void
    {
        $this->language = app(GetGuestMenuForBranchAction::class)->resolveLanguageForBranch($this->branchId, $this->language);
    }

    public function render(): View
    {
        return view('livewire.public-qr.guest-menu', [
            'guestMenu' => app(GetGuestMenuForBranchAction::class)->handle($this->branchId, $this->language),
        ]);
    }
}
