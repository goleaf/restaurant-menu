<?php

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\DeleteBranchAction;
use App\Actions\Branches\UpdateBranchAction;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Branches')]
class Index extends Component
{
    public Organization $organization;

    public Brand $brand;

    public string $name = '';

    public string $address = '';

    public string $city = '';

    public string $country = '';

    public string $timezone = 'Europe/Vilnius';

    public string $currency = 'EUR';

    public bool $isActive = true;

    public ?int $editingBranchId = null;

    public string $editingName = '';

    public string $editingAddress = '';

    public string $editingCity = '';

    public string $editingCountry = '';

    public string $editingTimezone = '';

    public string $editingCurrency = '';

    public bool $editingIsActive = true;

    public ?int $deletingBranchId = null;

    public bool $canManageBranches = false;

    public bool $canManageZones = false;

    public bool $canManageServicePoints = false;

    public bool $canChangeServicePointStatus = false;

    public bool $canManageStaff = false;

    public function mount(Organization $organization, Brand $brand): void
    {
        $this->organization = $organization;
        $this->brand = $brand;

        if ($brand->organization_id !== $organization->id) {
            abort(403);
        }

        if (! $this->currentUser()->canAccessOrganization($organization)) {
            abort(403);
        }

        $this->canManageBranches = $this->currentUser()->canManageOrganizationBranches($organization);
        $this->canManageZones = $this->currentUser()->hasPermission(SystemPermission::ManageZones, $organization);
        $this->canManageServicePoints = $this->currentUser()->hasPermission(SystemPermission::ManageServicePoints, $organization);
        $this->canChangeServicePointStatus = $this->canManageServicePoints
            || $this->currentUser()->hasOrganizationRole($organization, SystemRole::Waiter);
        $this->canManageStaff = $this->currentUser()->hasPermission(SystemPermission::ManageStaff, $organization);
    }

    public function create(CreateBranchAction $createBranch): void
    {
        $this->authorizeBranchManagement();

        $validated = $this->validate($this->branchRules());

        $createBranch->handle($this->brand, $this->branchPayload($validated));

        $this->resetCreateForm();
        unset($this->branches);

        Flux::toast(variant: 'success', text: __('Branch created.'));
    }

    public function startEditing(int $branchId): void
    {
        $this->authorizeBranchManagement();

        $branch = $this->findBrandBranch($branchId);

        $this->editingBranchId = $branch->id;
        $this->editingName = $branch->name;
        $this->editingAddress = $branch->address;
        $this->editingCity = $branch->city;
        $this->editingCountry = $branch->country;
        $this->editingTimezone = $branch->timezone;
        $this->editingCurrency = $branch->currency;
        $this->editingIsActive = $branch->is_active;
        $this->deletingBranchId = null;
    }

    public function cancelEditing(): void
    {
        $this->reset(
            'editingBranchId',
            'editingName',
            'editingAddress',
            'editingCity',
            'editingCountry',
            'editingTimezone',
            'editingCurrency',
        );

        $this->editingIsActive = true;
    }

    public function update(UpdateBranchAction $updateBranch): void
    {
        $this->authorizeBranchManagement();

        if ($this->editingBranchId === null) {
            return;
        }

        $validated = $this->validate($this->branchRules('editing', $this->editingBranchId));

        $updateBranch->handle(
            $this->findBrandBranch($this->editingBranchId),
            $this->branchPayload($validated, 'editing'),
        );

        $this->cancelEditing();
        unset($this->branches);

        Flux::toast(variant: 'success', text: __('Branch updated.'));
    }

    public function confirmDelete(int $branchId): void
    {
        $this->authorizeBranchManagement();

        $branch = $this->findBrandBranch($branchId);

        $this->deletingBranchId = $branch->id;
        $this->cancelEditing();
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingBranchId');
    }

    public function delete(DeleteBranchAction $deleteBranch): void
    {
        $this->authorizeBranchManagement();

        if ($this->deletingBranchId === null) {
            return;
        }

        $deleteBranch->handle($this->findBrandBranch($this->deletingBranchId));

        $this->cancelDelete();
        unset($this->branches);

        Flux::toast(variant: 'success', text: __('Branch deleted.'));
    }

    /**
     * @return EloquentCollection<int, Branch>
     */
    #[Computed]
    public function branches(): EloquentCollection
    {
        return $this->brand
            ->branches()
            ->select([
                'id',
                'organization_id',
                'brand_id',
                'name',
                'address',
                'city',
                'country',
                'timezone',
                'currency',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.index');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function branchRules(string $prefix = '', ?int $ignoreBranchId = null): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;
        $nameField = $fieldPrefix === '' ? 'name' : $fieldPrefix.'Name';

        $uniqueRule = Rule::unique((new Branch)->getTable(), 'name')
            ->where(fn ($query) => $query->where('brand_id', $this->brand->id));

        if ($ignoreBranchId !== null) {
            $uniqueRule->ignore($ignoreBranchId);
        }

        return [
            $nameField => ['required', 'string', 'max:160', $uniqueRule],
            $fieldPrefix === '' ? 'address' : $fieldPrefix.'Address' => ['required', 'string', 'max:255'],
            $fieldPrefix === '' ? 'city' : $fieldPrefix.'City' => ['required', 'string', 'max:120'],
            $fieldPrefix === '' ? 'country' : $fieldPrefix.'Country' => ['required', 'string', 'max:120'],
            $fieldPrefix === '' ? 'timezone' : $fieldPrefix.'Timezone' => ['required', 'timezone', 'max:64'],
            $fieldPrefix === '' ? 'currency' : $fieldPrefix.'Currency' => ['required', 'string', 'size:3'],
            $fieldPrefix === '' ? 'isActive' : $fieldPrefix.'IsActive' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool}
     */
    private function branchPayload(array $validated, string $prefix = ''): array
    {
        return [
            'name' => $validated[$prefix === '' ? 'name' : $prefix.'Name'],
            'address' => $validated[$prefix === '' ? 'address' : $prefix.'Address'],
            'city' => $validated[$prefix === '' ? 'city' : $prefix.'City'],
            'country' => $validated[$prefix === '' ? 'country' : $prefix.'Country'],
            'timezone' => $validated[$prefix === '' ? 'timezone' : $prefix.'Timezone'],
            'currency' => strtoupper($validated[$prefix === '' ? 'currency' : $prefix.'Currency']),
            'is_active' => (bool) $validated[$prefix === '' ? 'isActive' : $prefix.'IsActive'],
        ];
    }

    private function resetCreateForm(): void
    {
        $this->reset('name', 'address', 'city', 'country');
        $this->timezone = 'Europe/Vilnius';
        $this->currency = 'EUR';
        $this->isActive = true;
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function authorizeBranchManagement(): void
    {
        if (! $this->canManageBranches) {
            abort(403);
        }
    }

    private function findBrandBranch(int $branchId): Branch
    {
        return $this->brand
            ->branches()
            ->select([
                'id',
                'organization_id',
                'brand_id',
                'name',
                'address',
                'city',
                'country',
                'timezone',
                'currency',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->whereKey($branchId)
            ->firstOrFail();
    }
}
