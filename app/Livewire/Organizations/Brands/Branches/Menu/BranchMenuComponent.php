<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

abstract class BranchMenuComponent extends Component
{
    #[Locked]
    public int $organizationId;

    #[Locked]
    public int $brandId;

    #[Locked]
    public int $branchId;

    protected function initializeBranchContext(int $organizationId, int $brandId, int $branchId): void
    {
        $this->organizationId = $organizationId;
        $this->brandId = $brandId;
        $this->branchId = $branchId;

        if (
            $this->brand->organization_id !== $this->organization->id
            || $this->branch->organization_id !== $this->organization->id
            || $this->branch->brand_id !== $this->brand->id
        ) {
            abort(403);
        }

        Gate::forUser($this->currentUser())->authorize('view', $this->branch);
    }

    protected function authorizeBranchAbility(string $ability): void
    {
        Gate::forUser($this->currentUser())->authorize($ability, $this->branch);
    }

    protected function branchAllows(string $ability): bool
    {
        return Gate::forUser($this->currentUser())->allows($ability, $this->branch);
    }

    #[Computed]
    public function organization(): Organization
    {
        return Organization::query()
            ->select(['id', 'name'])
            ->findOrFail($this->organizationId);
    }

    #[Computed]
    public function brand(): Brand
    {
        return Brand::query()
            ->select(['id', 'organization_id', 'name'])
            ->findOrFail($this->brandId);
    }

    #[Computed]
    public function branch(): Branch
    {
        return Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'currency', 'timezone'])
            ->findOrFail($this->branchId);
    }

    protected function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
