<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\KitchenDepartments\CreateKitchenDepartmentAction;
use App\Actions\KitchenDepartments\DeleteKitchenDepartmentAction;
use App\Actions\KitchenDepartments\SetKitchenDepartmentActiveAction;
use App\Actions\KitchenDepartments\UpdateKitchenDepartmentAction;
use App\Enums\KitchenDepartmentType;
use App\Models\KitchenDepartment;
use App\Services\Menus\CatalogData;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/** @property-read EloquentCollection<int, KitchenDepartment> $departments */
class KitchenDepartments extends BranchMenuComponent
{
    private CatalogData $menuQueries;

    public string $departmentName = '';

    public string $departmentType = 'kitchen';

    public int $departmentSortOrder = 0;

    public bool $departmentIsActive = true;

    public ?int $editingDepartmentId = null;

    public string $editingDepartmentName = '';

    public string $editingDepartmentType = 'kitchen';

    public int $editingDepartmentSortOrder = 0;

    public bool $editingDepartmentIsActive = true;

    public function boot(CatalogData $menuQueries): void
    {
        $this->menuQueries = $menuQueries;
    }

    public function mount(int $organizationId, int $brandId, int $branchId): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('manageMenu');
    }

    public function createKitchenDepartment(CreateKitchenDepartmentAction $createDepartment): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->departmentName = trim($this->departmentName);
        $validated = $this->validate($this->rulesForDepartment());

        $createDepartment->handle($this->branch, [
            'type' => $validated['departmentType'],
            'name' => $validated['departmentName'],
            'sort_order' => (int) $validated['departmentSortOrder'],
            'is_active' => (bool) $validated['departmentIsActive'],
        ]);

        $this->resetCreateForm();
        $this->refreshDepartments();
        $this->notifySiblings();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.kitchen_department_cre'));
    }

    public function startEditingKitchenDepartment(int $departmentId): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $department = $this->findDepartment($departmentId);

        $this->editingDepartmentId = $department->id;
        $this->editingDepartmentName = $department->name;
        $this->editingDepartmentType = $department->type->value;
        $this->editingDepartmentSortOrder = $department->sort_order;
        $this->editingDepartmentIsActive = $department->is_active;
    }

    public function cancelKitchenDepartmentEditing(): void
    {
        $this->reset('editingDepartmentId', 'editingDepartmentName');
        $this->editingDepartmentType = KitchenDepartmentType::Kitchen->value;
        $this->editingDepartmentSortOrder = 0;
        $this->editingDepartmentIsActive = true;
    }

    public function updateKitchenDepartment(UpdateKitchenDepartmentAction $updateDepartment): void
    {
        $this->authorizeBranchAbility('manageMenu');

        if ($this->editingDepartmentId === null) {
            return;
        }

        $this->editingDepartmentName = trim($this->editingDepartmentName);
        $validated = $this->validate($this->rulesForDepartment('editing'));

        $updateDepartment->handle($this->findDepartment($this->editingDepartmentId), [
            'type' => $validated['editingDepartmentType'],
            'name' => $validated['editingDepartmentName'],
            'sort_order' => (int) $validated['editingDepartmentSortOrder'],
            'is_active' => (bool) $validated['editingDepartmentIsActive'],
        ]);

        $this->cancelKitchenDepartmentEditing();
        $this->refreshDepartments();
        $this->notifySiblings();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.kitchen_department_upd'));
    }

    public function setKitchenDepartmentActive(int $departmentId, bool $isActive, SetKitchenDepartmentActiveAction $setActive): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $setActive->handle($this->findDepartment($departmentId), $isActive);
        $this->refreshDepartments();
        $this->notifySiblings();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.kitchen_department_upd'));
    }

    public function deleteKitchenDepartment(int $departmentId, DeleteKitchenDepartmentAction $deleteDepartment): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $deleteDepartment->handle($this->findDepartment($departmentId));
        $this->cancelKitchenDepartmentEditing();
        $this->refreshDepartments();
        $this->notifySiblings();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.kitchen_department_rem'));
    }

    #[On('branch-menu-updated')]
    public function refreshDepartments(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        unset($this->departments);
    }

    /**
     * @return EloquentCollection<int, KitchenDepartment>
     */
    #[Computed]
    public function departments(): EloquentCollection
    {
        return $this->menuQueries->kitchenDepartments($this->branch);
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.menu.kitchen-departments', [
            'kitchenDepartmentRows' => $this->departments->map(fn (KitchenDepartment $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'type_color' => $department->type->badgeColor(),
                'localized_type' => __($department->type->label()),
                'is_active' => $department->is_active,
                'menu_items_count' => $department->menu_items_count,
                'sort_order' => $department->sort_order,
            ])->all(),
            'kitchenDepartmentTypeOptions' => KitchenDepartmentType::options(),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rulesForDepartment(string $prefix = ''): array
    {
        $nameField = $prefix === '' ? 'departmentName' : $prefix.'DepartmentName';
        $uniqueName = Rule::unique((new KitchenDepartment)->getTable(), 'name')
            ->where(fn ($query) => $query->where('branch_id', $this->branchId));

        if ($prefix === 'editing' && $this->editingDepartmentId !== null) {
            $uniqueName->ignore($this->editingDepartmentId);
        }

        $rules = RestaurantValidationRules::kitchenDepartment($prefix);
        $rules[$nameField] = ['required', 'string', 'max:120', $uniqueName];

        return $rules;
    }

    private function findDepartment(int $departmentId): KitchenDepartment
    {
        return $this->menuQueries->findKitchenDepartment($this->branch, $departmentId);
    }

    private function resetCreateForm(): void
    {
        $this->reset('departmentName');
        $this->departmentType = KitchenDepartmentType::Kitchen->value;
        $this->departmentSortOrder = 0;
        $this->departmentIsActive = true;
    }

    private function notifySiblings(): void
    {
        $this->dispatch('branch-menu-updated');
    }

    protected function catalogData(): CatalogData
    {
        return $this->menuQueries;
    }
}
