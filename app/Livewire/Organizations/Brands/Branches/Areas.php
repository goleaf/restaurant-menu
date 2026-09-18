<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Services\Branches\FloorLegacyEntryQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Areas extends Component
{
    public function mount(Organization $organization, Brand $brand, Branch $branch, FloorLegacyEntryQuery $entry): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);
        $this->redirect($entry->destination($actor, $organization, $brand, $branch, ['view' => 'zones', 'area_search' => request()->query('q', ''), 'area_lifecycle' => request()->query('lifecycle', 'active'), 'area_type' => request()->query('type', 'all'), 'area_active' => request()->query('active', 'all'), 'area_sort' => request()->query('sort', 'position')]), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.service-points.legacy-entry');
    }
}
