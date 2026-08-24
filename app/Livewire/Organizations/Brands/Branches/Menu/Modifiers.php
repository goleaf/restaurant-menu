<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Modifiers\AssignModifierGroupToMenuItemAction;
use App\Actions\Modifiers\CreateModifierGroupAction;
use App\Actions\Modifiers\CreateModifierOptionAction;
use App\Actions\Modifiers\DeleteModifierGroupAction;
use App\Actions\Modifiers\DeleteModifierOptionAction;
use App\Actions\Modifiers\UnassignModifierGroupFromMenuItemAction;
use App\Actions\Modifiers\UpdateModifierGroupAction;
use App\Actions\Modifiers\UpdateModifierOptionAction;
use App\Enums\SupportedLocale;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Services\Menus\CatalogData;
use App\Support\MoneyFormatter;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/** @property-read EloquentCollection<int, ModifierGroup> $groups */
class Modifiers extends BranchMenuComponent
{
    private CatalogData $menuQueries;

    public string $modifierGroupName = '';

    public bool $modifierGroupIsRequired = false;

    public int $modifierGroupMinSelect = 0;

    public int $modifierGroupMaxSelect = 1;

    public int $modifierGroupSortOrder = 0;

    /** @var array<string, string> */
    public array $modifierGroupTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    public ?int $editingModifierGroupId = null;

    public string $editingModifierGroupName = '';

    public bool $editingModifierGroupIsRequired = false;

    public int $editingModifierGroupMinSelect = 0;

    public int $editingModifierGroupMaxSelect = 1;

    public int $editingModifierGroupSortOrder = 0;

    /** @var array<string, string> */
    public array $editingModifierGroupTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    public string $modifierOptionGroupId = '';

    public string $modifierOptionName = '';

    public string $modifierOptionPriceDelta = '0.00';

    public bool $modifierOptionIsAvailable = true;

    public int $modifierOptionSortOrder = 0;

    /** @var array<string, string> */
    public array $modifierOptionTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    public ?int $editingModifierOptionId = null;

    public string $editingModifierOptionName = '';

    public string $editingModifierOptionPriceDelta = '0.00';

    public bool $editingModifierOptionIsAvailable = true;

    public int $editingModifierOptionSortOrder = 0;

    /** @var array<string, string> */
    public array $editingModifierOptionTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    public string $modifierItemMenuId = '';

    public string $modifierItemId = '';

    public string $modifierItemGroupId = '';

    #[Locked]
    public bool $canChangePrices = false;

    #[Locked]
    public bool $canChangeAvailability = false;

    public function boot(CatalogData $menuQueries): void
    {
        $this->menuQueries = $menuQueries;
    }

    public function mount(int $organizationId, int $brandId, int $branchId): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('manageMenu');
        $this->canChangePrices = $this->branchAllows('changeMenuPrices');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');

        $this->modifierItemMenuId = $this->firstMenuId();
        $this->modifierItemId = $this->firstItemId($this->modifierItemMenuId);
        $firstGroupId = $this->firstModifierGroupId();
        $this->modifierOptionGroupId = $firstGroupId;
        $this->modifierItemGroupId = $firstGroupId;
    }

    public function updatedModifierItemMenuId(): void
    {
        $this->modifierItemId = $this->firstItemId($this->modifierItemMenuId);
    }

    public function createModifierGroup(CreateModifierGroupAction $createGroup): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->modifierGroupName = trim($this->modifierGroupName);
        $rules = RestaurantValidationRules::modifierGroup();
        $rules['modifierGroupName'][] = $this->groupNameUniqueRule();
        $validated = $this->validate([
            ...$rules,
            ...RestaurantValidationRules::translatedNames('modifierGroupTranslations'),
        ]);

        $group = $createGroup->handle($this->branch, [
            'name' => $validated['modifierGroupName'],
            'is_required' => (bool) $validated['modifierGroupIsRequired'],
            'min_select' => (int) $validated['modifierGroupMinSelect'],
            'max_select' => (int) $validated['modifierGroupMaxSelect'],
            'sort_order' => (int) $validated['modifierGroupSortOrder'],
            'translations' => $validated['modifierGroupTranslations'],
        ]);

        $this->modifierOptionGroupId = (string) $group->id;
        $this->modifierItemGroupId = (string) $group->id;
        $this->resetGroupForm();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_created'));
    }

    public function startEditingModifierGroup(int $modifierGroupId): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $group = $this->findGroup($modifierGroupId);
        $this->editingModifierGroupId = $group->id;
        $this->editingModifierGroupName = $group->name;
        $this->editingModifierGroupIsRequired = $group->is_required;
        $this->editingModifierGroupMinSelect = $group->min_select;
        $this->editingModifierGroupMaxSelect = $group->max_select;
        $this->editingModifierGroupSortOrder = $group->sort_order;
        $this->editingModifierGroupTranslations = $this->menuQueries->nameTranslationValues($group);
        $this->cancelModifierOptionEditing();
    }

    public function cancelModifierGroupEditing(): void
    {
        $this->reset('editingModifierGroupId', 'editingModifierGroupName');
        $this->editingModifierGroupIsRequired = false;
        $this->editingModifierGroupMinSelect = 0;
        $this->editingModifierGroupMaxSelect = 1;
        $this->editingModifierGroupSortOrder = 0;
        $this->editingModifierGroupTranslations = $this->emptyTranslations();
    }

    public function updateModifierGroup(UpdateModifierGroupAction $updateGroup): void
    {
        $this->authorizeBranchAbility('manageMenu');

        if ($this->editingModifierGroupId === null) {
            return;
        }

        $this->editingModifierGroupName = trim($this->editingModifierGroupName);
        $rules = RestaurantValidationRules::modifierGroup('editing');
        $rules['editingModifierGroupName'][] = $this->groupNameUniqueRule($this->editingModifierGroupId);
        $validated = $this->validate([
            ...$rules,
            ...RestaurantValidationRules::translatedNames('editingModifierGroupTranslations'),
        ]);
        $updateGroup->handle($this->findGroup($this->editingModifierGroupId), [
            'name' => $validated['editingModifierGroupName'],
            'is_required' => (bool) $validated['editingModifierGroupIsRequired'],
            'min_select' => (int) $validated['editingModifierGroupMinSelect'],
            'max_select' => (int) $validated['editingModifierGroupMaxSelect'],
            'sort_order' => (int) $validated['editingModifierGroupSortOrder'],
            'translations' => $validated['editingModifierGroupTranslations'],
        ]);

        $this->cancelModifierGroupEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_updated'));
    }

    public function deleteModifierGroup(int $modifierGroupId, DeleteModifierGroupAction $deleteGroup): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $deleteGroup->handle($this->findGroup($modifierGroupId));

        if ($this->modifierOptionGroupId === (string) $modifierGroupId) {
            $this->modifierOptionGroupId = $this->firstModifierGroupId();
        }
        if ($this->modifierItemGroupId === (string) $modifierGroupId) {
            $this->modifierItemGroupId = $this->firstModifierGroupId();
        }

        $this->cancelModifierGroupEditing();
        $this->cancelModifierOptionEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_removed'));
    }

    public function createModifierOption(CreateModifierOptionAction $createOption): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->modifierOptionName = trim($this->modifierOptionName);
        $this->refreshMutationCapabilities();
        $rules = RestaurantValidationRules::modifierOption(
            canChangePrices: $this->canChangePrices,
            canChangeAvailability: $this->canChangeAvailability,
        );
        $rules['modifierOptionGroupId'] = ['required', 'integer', $this->groupRule()];
        $rules['modifierOptionName'][] = $this->optionNameUniqueRule((int) $this->modifierOptionGroupId);
        $rules = [
            ...$rules,
            ...RestaurantValidationRules::translatedNames('modifierOptionTranslations'),
        ];
        $validated = $this->validate($rules);
        $group = $this->findGroup((int) $validated['modifierOptionGroupId']);
        $createOption->handle($this->currentUser(), $this->branch, $group, $this->optionData($validated));
        $this->resetOptionForm((string) $group->id);
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_create'));
    }

    public function startEditingModifierOption(int $modifierOptionId): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $option = $this->findOption($modifierOptionId);
        $this->editingModifierOptionId = $option->id;
        $this->editingModifierOptionName = $option->name;
        $this->editingModifierOptionPriceDelta = MoneyFormatter::centsToDecimal($option->price_delta_cents);
        $this->editingModifierOptionIsAvailable = $option->is_available;
        $this->editingModifierOptionSortOrder = $option->sort_order;
        $this->editingModifierOptionTranslations = $this->menuQueries->nameTranslationValues($option);
        $this->cancelModifierGroupEditing();
    }

    public function cancelModifierOptionEditing(): void
    {
        $this->reset('editingModifierOptionId', 'editingModifierOptionName');
        $this->editingModifierOptionPriceDelta = '0.00';
        $this->editingModifierOptionIsAvailable = true;
        $this->editingModifierOptionSortOrder = 0;
        $this->editingModifierOptionTranslations = $this->emptyTranslations();
    }

    public function updateModifierOption(UpdateModifierOptionAction $updateOption): void
    {
        $this->authorizeBranchAbility('manageMenu');

        if ($this->editingModifierOptionId === null) {
            return;
        }

        $this->editingModifierOptionName = trim($this->editingModifierOptionName);
        $this->refreshMutationCapabilities();
        $option = $this->findOption($this->editingModifierOptionId);
        $rules = RestaurantValidationRules::modifierOption(
            prefix: 'editing',
            canChangePrices: $this->canChangePrices,
            canChangeAvailability: $this->canChangeAvailability,
        );
        $rules['editingModifierOptionName'][] = $this->optionNameUniqueRule(
            $option->modifier_group_id,
            $option->id,
        );
        $validated = $this->validate([
            ...$rules,
            ...RestaurantValidationRules::translatedNames('editingModifierOptionTranslations'),
        ]);
        $updateOption->handle($this->currentUser(), $this->branch, $option, $this->optionData($validated, 'editing'));
        $this->cancelModifierOptionEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_update'));
    }

    public function deleteModifierOption(int $modifierOptionId, DeleteModifierOptionAction $deleteOption): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $deleteOption->handle($this->findOption($modifierOptionId));
        $this->cancelModifierOptionEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_remove'));
    }

    public function attachModifierGroupToItem(AssignModifierGroupToMenuItemAction $assignGroup): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $validated = $this->validate([
            'modifierItemMenuId' => ['required', 'integer', $this->menuRule()],
            'modifierItemId' => ['required', 'integer', $this->itemRule($this->modifierItemMenuId)],
            'modifierItemGroupId' => ['required', 'integer', $this->groupRule()],
        ]);
        $item = $this->findItem((int) $validated['modifierItemId']);
        $group = $this->findGroup((int) $validated['modifierItemGroupId']);
        $assignGroup->handle($this->branch, $item, $group);
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_assigne'));
    }

    public function detachModifierGroupFromItem(
        int $itemId,
        int $modifierGroupId,
        UnassignModifierGroupFromMenuItemAction $unassignGroup,
    ): void {
        $this->authorizeBranchAbility('manageMenu');
        $unassignGroup->handle($this->branch, $this->findItem($itemId), $this->findGroup($modifierGroupId));
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_unassig'));
    }

    #[On('branch-menu-updated')]
    public function refreshData(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        unset($this->groups);
    }

    /** @return EloquentCollection<int, ModifierGroup> */
    #[Computed]
    public function groups(): EloquentCollection
    {
        return $this->menuQueries->modifierGroups($this->branch);
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.menu.modifiers', [
            'modifierGroupRows' => $this->groups->map(fn (ModifierGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'is_required' => $group->is_required,
                'min_select' => $group->min_select,
                'max_select' => $group->max_select,
                'items_count' => $group->items_count,
                'sort_order' => $group->sort_order,
                'translations' => $this->menuQueries->nameTranslationValues($group),
                'options' => $group->options->map(fn (ModifierOption $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'formatted_price_delta' => MoneyFormatter::formatSignedCents($option->price_delta_cents, $this->branch->currency),
                    'is_available' => $option->is_available,
                    'sort_order' => $option->sort_order,
                    'translations' => $this->menuQueries->nameTranslationValues($option),
                ])->all(),
            ])->all(),
            'modifierGroupOptions' => $this->groups->map(fn (ModifierGroup $group): array => [
                'value' => (string) $group->id,
                'label' => $group->name,
            ])->values()->all(),
            'menuOptions' => $this->menuOptions(),
            'modifierItemOptions' => $this->itemOptions(),
            'languageOptions' => SupportedLocale::labels(),
        ]);
    }

    /** @return list<array{value: string, label: string}> */
    private function menuOptions(): array
    {
        return $this->menuQueries->menuOptions($this->branch);
    }

    /** @return list<array{value: string, label: string}> */
    private function itemOptions(): array
    {
        return $this->menuQueries->itemOptions($this->branchId, $this->modifierItemMenuId);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, price_delta: string, is_available: bool, sort_order: int}
     */
    private function optionData(array $validated, string $prefix = ''): array
    {
        $field = static fn (string $name): string => $prefix === '' ? $name : $prefix.ucfirst($name);
        $data = [
            'name' => (string) $validated[$field('modifierOptionName')],
            'sort_order' => (int) $validated[$field('modifierOptionSortOrder')],
            'translations' => $validated[$field('modifierOptionTranslations')],
        ];

        if ($this->canChangePrices) {
            $data['price_delta'] = $validated[$field('modifierOptionPriceDelta')];
        }

        if ($this->canChangeAvailability) {
            $data['is_available'] = (bool) $validated[$field('modifierOptionIsAvailable')];
        }

        return $data;
    }

    private function menuRule(): mixed
    {
        return Rule::exists((new Menu)->getTable(), 'id')->where(fn ($query) => $query->where('branch_id', $this->branchId));
    }

    private function itemRule(string $menuId): mixed
    {
        return Rule::exists((new MenuItem)->getTable(), 'id')->where(fn ($query) => $query->where('menu_id', (int) $menuId));
    }

    private function groupRule(): mixed
    {
        return Rule::exists((new ModifierGroup)->getTable(), 'id')->where(fn ($query) => $query->where('branch_id', $this->branchId));
    }

    private function groupNameUniqueRule(?int $ignoreGroupId = null): mixed
    {
        $rule = Rule::unique((new ModifierGroup)->getTable(), 'name')
            ->where(fn ($query) => $query->where('branch_id', $this->branchId));

        return $ignoreGroupId === null ? $rule : $rule->ignore($ignoreGroupId);
    }

    private function optionNameUniqueRule(int $groupId, ?int $ignoreOptionId = null): mixed
    {
        $rule = Rule::unique((new ModifierOption)->getTable(), 'name')
            ->where(fn ($query) => $query->where('modifier_group_id', $groupId));

        return $ignoreOptionId === null ? $rule : $rule->ignore($ignoreOptionId);
    }

    private function findGroup(int $id): ModifierGroup
    {
        return $this->menuQueries->findModifierGroup($this->branch, $id);
    }

    private function findOption(int $id): ModifierOption
    {
        return $this->menuQueries->findModifierOption($this->branchId, $id);
    }

    private function findItem(int $id): MenuItem
    {
        return $this->menuQueries->findModifierItem($this->branchId, $id);
    }

    private function firstMenuId(): string
    {
        return $this->menuQueries->firstMenuId($this->branch);
    }

    private function firstItemId(string $menuId): string
    {
        return $this->menuQueries->firstItemId($this->branchId, $menuId);
    }

    private function firstModifierGroupId(): string
    {
        return $this->menuQueries->firstModifierGroupId($this->branch);
    }

    private function resetGroupForm(): void
    {
        $this->reset('modifierGroupName');
        $this->modifierGroupIsRequired = false;
        $this->modifierGroupMinSelect = 0;
        $this->modifierGroupMaxSelect = 1;
        $this->modifierGroupSortOrder = 0;
        $this->modifierGroupTranslations = $this->emptyTranslations();
    }

    private function resetOptionForm(string $groupId): void
    {
        $this->reset('modifierOptionName');
        $this->modifierOptionGroupId = $groupId;
        $this->modifierOptionPriceDelta = '0.00';
        $this->modifierOptionIsAvailable = true;
        $this->modifierOptionSortOrder = 0;
        $this->modifierOptionTranslations = $this->emptyTranslations();
    }

    /** @return array<string, string> */
    private function emptyTranslations(): array
    {
        return array_fill_keys(SupportedLocale::values(), '');
    }

    private function changed(): void
    {
        unset($this->groups);
        $this->dispatch('branch-menu-updated');
    }

    private function refreshMutationCapabilities(): void
    {
        $this->canChangePrices = $this->branchAllows('changeMenuPrices');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');
    }

    protected function catalogData(): CatalogData
    {
        return $this->menuQueries;
    }
}
