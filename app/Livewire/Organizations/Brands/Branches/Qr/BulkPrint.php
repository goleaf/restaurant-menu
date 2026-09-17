<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Qr;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Branches\FloorLegacyEntryQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class BulkPrint extends Component
{
    public function mount(Organization $organization, Brand $brand, Branch $branch, FloorLegacyEntryQuery $entry): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);
        $this->redirect($entry->destination($actor, $organization, $brand, $branch, ["zone" => request()->query("area", "all")], ability: 'generateQr'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.service-points.legacy-entry');
    }
}
