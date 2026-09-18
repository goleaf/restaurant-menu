<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Modifiers\AssignModifierGroupToMenuItemAction;
use App\Actions\Modifiers\CloneModifierGroupForMenuItemAction;
use App\Actions\Modifiers\CreateModifierGroupAction;
use App\Actions\Modifiers\CreateModifierOptionAction;
use App\Actions\Modifiers\DeleteModifierGroupAction;
use App\Actions\Modifiers\DeleteModifierOptionAction;
use App\Actions\Modifiers\UnassignModifierGroupFromMenuItemAction;
use App\Actions\Modifiers\UpdateModifierGroupAction;
use App\Actions\Modifiers\UpdateModifierOptionAction;
use App\Enums\MenuOperationKind;
use App\Enums\SupportedLocale;
use App\Livewire\Forms\Menus\ModifierAssignmentForm;
use App\Livewire\Forms\Menus\ModifierGroupForm;
use App\Livewire\Forms\Menus\ModifierOptionForm;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Services\Menus\CatalogData;
use App\Services\Menus\DishConfigurationData;
use App\Support\MoneyFormatter;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\WithPagination;

/** @property-read LengthAwarePaginator<int, ModifierGroup> $groups */
class Modifiers extends BranchMenuComponent
{
    use WithPagination;

    private CatalogData $menuQueries;

    private DishConfigurationData $configurationQueries;

    #[Locked]
    public ?int $itemId = null;

    #[Locked]
    public int $editingGroupVersion = 0;

    #[Locked]
    public int $editingOptionVersion = 0;

    #[Locked]
    public ?int $editingOptionGroupId = null;

    #[Locked]
    public int $createOptionVersion = 0;

    #[Locked]
    public int $linksVersion = 0;

    #[Locked]
    public int $assignmentGroupVersion = 0;

    #[Locked]
    public array $displayedGroupVersions = [];

    #[Locked]
    public array $displayedOptionGroups = [];

    #[Locked]
    public array $deleteRequests = [];

    #[Locked]
    public ?int $optionsGroupId = null;

    #[Locked]
    public int $optionsPage = 1;

    #[Locked]
    public int $editingGroupUses = 0;

    #[Locked]
    public int $editingOptionUses = 0;

    #[Locked]
    public array $requestIds = [];

    #[Locked]
    public ?int $cloningGroupId = null;

    #[Locked]
    public int $cloneGroupVersion = 0;

    #[Locked]
    public int $cloneLinksVersion = 0;

    public mixed $cloneName = '';

    public mixed $groupSearch = '';

    public ModifierGroupForm $group;

    public ModifierGroupForm $editingGroup;

    public ModifierOptionForm $option;

    public ModifierOptionForm $editingOption;

    public ModifierAssignmentForm $assignment;

    #[Locked]
    public ?int $editingModifierGroupId = null;

    #[Locked]
    public ?int $editingModifierOptionId = null;

    #[Locked]
    public bool $canChangePrices = false;

    #[Locked]
    public bool $canChangeAvailability = false;

    public function boot(CatalogData $menuQueries, DishConfigurationData $configurationQueries): void
    {
        $this->menuQueries = $menuQueries;
        $this->configurationQueries = $configurationQueries;
    }

    public function mount(int $organizationId, int $brandId, int $branchId, ?int $itemId = null): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('manageMenu');
        $this->canChangePrices = $this->branchAllows('changeMenuPrices');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');
        $this->itemId = $itemId;
        if ($itemId !== null) {
            $item = $this->configurationQueries->item($this->branch, $itemId);
            $this->assignment->modifierItemMenuId = (string) $item->menu_id;
            $this->assignment->modifierItemId = (string) $item->id;
            $this->linksVersion = $item->modifier_links_version;
        }
        $firstGroupId = $this->firstModifierGroupId();
        $this->option->modifierOptionGroupId = $this->itemId === null ? $firstGroupId
            : ($this->configurationQueries->groupOptions($this->branch, '', $this->itemId)[0]['value'] ?? '');
        $this->assignment->modifierItemGroupId = $firstGroupId;
        foreach (['create_group', 'create_option', 'edit_group', 'edit_option', 'assign', 'clone'] as $operation) {
            $this->requestIds[$operation] = (string) Str::uuid();
        }
        $this->updatedOptionModifierOptionGroupId();
        $this->updatedAssignmentModifierItemGroupId();
    }

    public function updatedAssignmentModifierItemMenuId(): void
    {
        $this->assertEmbeddedSelection();
        if ($this->itemId !== null) {
            return;
        }
        $this->assignment->modifierItemId = $this->firstItemId($this->assignment->modifierItemMenuId);
    }

    public function updatedOptionModifierOptionGroupId(): void
    {
        $id = $this->selectionValue($this->option->modifierOptionGroupId);
        $this->createOptionVersion = ctype_digit($id) ? $this->findGroup((int) $id)->content_version : 0;
        $this->requestIds['create_option'] = (string) Str::uuid();
    }

    public function updatedAssignmentModifierItemGroupId(): void
    {
        $id = $this->selectionValue($this->assignment->modifierItemGroupId);
        $this->assignmentGroupVersion = ctype_digit($id) ? $this->findGroup((int) $id)->content_version : 0;
        $this->requestIds['assign'] = (string) Str::uuid();
    }

    public function createModifierGroup(CreateModifierGroupAction $createGroup): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        $validated = $this->group->validated($this->branch, $this->configurationQueries->replayedResultId(
            $this->currentUser(), $this->branch, $this->requestIds['create_group'], MenuOperationKind::ModifierChange));

        $group = $createGroup->handle($this->currentUser(), $this->branch, [
            'name' => $validated['modifierGroupName'],
            'is_required' => (bool) $validated['modifierGroupIsRequired'],
            'min_select' => (int) $validated['modifierGroupMinSelect'],
            'max_select' => (int) $validated['modifierGroupMaxSelect'],
            'sort_order' => (int) $validated['modifierGroupSortOrder'],
            'translations' => $validated['modifierGroupTranslations'],
        ], $this->requestIds['create_group'], $this->itemId === null ? null : $this->findItem($this->itemId), $this->linksVersion);

        if ($this->itemId !== null) {
            $this->linksVersion = $this->configurationQueries->item($this->branch, $this->itemId)->modifier_links_version;
        }

        $this->option->modifierOptionGroupId = (string) $group->id;
        $this->assignment->modifierItemGroupId = (string) $group->id;
        $this->resetGroupForm();
        $this->requestIds['create_group'] = (string) Str::uuid();
        $this->updatedOptionModifierOptionGroupId();
        $this->updatedAssignmentModifierItemGroupId();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_created'));
    }

    public function startEditingModifierGroup(int $modifierGroupId): void
    {
        $this->authorizeBranchAbility('manageMenu');
        if ($this->editingModifierGroupId !== null) {
            $this->rejectAnotherEditor($this->editingModifierGroupId, $modifierGroupId);

            return;
        }
        $group = $this->findGroup($modifierGroupId);
        $this->assertGroupInDish($group);
        $this->editingGroupVersion = $group->content_version;
        $this->editingGroupUses = $this->configurationQueries->groupUses($this->branch, $group);
        $this->requestIds['edit_group'] = (string) Str::uuid();
        $this->editingModifierGroupId = $group->id;
        $this->editingGroup->modifierGroupName = $group->name;
        $this->editingGroup->modifierGroupIsRequired = $group->is_required;
        $this->editingGroup->modifierGroupMinSelect = $group->min_select;
        $this->editingGroup->modifierGroupMaxSelect = $group->max_select;
        $this->editingGroup->modifierGroupSortOrder = $group->sort_order;
        $this->editingGroup->modifierGroupTranslations = $this->menuQueries->nameTranslationValues($group);
    }

    public function cancelModifierGroupEditing(): void
    {
        $this->editingModifierGroupId = null;
        $this->editingGroup->reset();
        $this->editingGroup->modifierGroupIsRequired = false;
        $this->editingGroup->modifierGroupMinSelect = 0;
        $this->editingGroup->modifierGroupMaxSelect = 1;
        $this->editingGroup->modifierGroupSortOrder = 0;
        $this->editingGroup->modifierGroupTranslations = $this->emptyTranslations();
    }

    public function updateModifierGroup(UpdateModifierGroupAction $updateGroup): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();

        if ($this->editingModifierGroupId === null) {
            return;
        }

        $this->assertGroupInDish($this->findGroup($this->editingModifierGroupId));
        $validated = $this->editingGroup->validated($this->branch, $this->editingModifierGroupId);
        $updateGroup->handle($this->currentUser(), $this->branch, $this->findGroup($this->editingModifierGroupId), [
            'name' => $validated['modifierGroupName'],
            'is_required' => (bool) $validated['modifierGroupIsRequired'],
            'min_select' => (int) $validated['modifierGroupMinSelect'],
            'max_select' => (int) $validated['modifierGroupMaxSelect'],
            'sort_order' => (int) $validated['modifierGroupSortOrder'],
            'translations' => $validated['modifierGroupTranslations'],
        ], $this->editingGroupVersion, $this->requestIds['edit_group']);

        $this->cancelModifierGroupEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_updated'));
    }

    public function deleteModifierGroup(int $modifierGroupId, DeleteModifierGroupAction $deleteGroup): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        abort_unless(isset($this->deleteRequests['group_'.$modifierGroupId]), 403);
        $deleteGroup->handle($this->currentUser(), $this->branch, $modifierGroupId, $this->displayedGroupVersions[$modifierGroupId] ?? -1, $this->deleteRequests['group_'.$modifierGroupId]);

        if ($this->option->modifierOptionGroupId === (string) $modifierGroupId) {
            $this->option->modifierOptionGroupId = $this->firstModifierGroupId();
        }
        if ($this->assignment->modifierItemGroupId === (string) $modifierGroupId) {
            $this->assignment->modifierItemGroupId = $this->firstModifierGroupId();
        }

        if ($this->editingModifierGroupId === $modifierGroupId) {
            $this->cancelModifierGroupEditing();
        }
        if ($this->editingOptionGroupId === $modifierGroupId) {
            $this->cancelModifierOptionEditing();
        }
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_removed'));
    }

    public function createModifierOption(CreateModifierOptionAction $createOption): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        $this->refreshMutationCapabilities();
        $validated = $this->option->validated($this->branch, $this->canChangePrices, $this->canChangeAvailability,
            ignoreId: $this->configurationQueries->replayedResultId($this->currentUser(), $this->branch, $this->requestIds['create_option'], MenuOperationKind::ModifierChange));
        $group = $this->findGroup((int) $validated['modifierOptionGroupId']);
        $this->assertGroupInDish($group);
        $createOption->handle($this->currentUser(), $this->branch, $group, $this->optionData($validated), $this->createOptionVersion, $this->requestIds['create_option']);
        $this->resetOptionForm((string) $group->id);
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_create'));
    }

    public function startEditingModifierOption(int $modifierOptionId): void
    {
        $this->authorizeBranchAbility('manageMenu');
        if ($this->editingModifierOptionId !== null) {
            $this->rejectAnotherEditor($this->editingModifierOptionId, $modifierOptionId);

            return;
        }
        $option = $this->findOption($modifierOptionId);
        $group = $this->findGroup($option->modifier_group_id);
        $this->assertGroupInDish($group);
        $this->editingOptionVersion = $group->content_version;
        $this->editingOptionGroupId = $group->id;
        $this->editingOptionUses = $this->configurationQueries->groupUses($this->branch, $group);
        $this->requestIds['edit_option'] = (string) Str::uuid();
        $this->editingModifierOptionId = $option->id;
        $this->editingOption->modifierOptionName = $option->name;
        $this->editingOption->modifierOptionPriceDelta = MoneyFormatter::centsToDecimal($option->price_delta_cents);
        $this->editingOption->modifierOptionIsAvailable = $option->is_available;
        $this->editingOption->modifierOptionSortOrder = $option->sort_order;
        $this->editingOption->modifierOptionTranslations = $this->menuQueries->nameTranslationValues($option);
    }

    public function cancelModifierOptionEditing(): void
    {
        $this->editingModifierOptionId = null;
        $this->editingOption->reset();
        $this->editingOption->modifierOptionPriceDelta = '0.00';
        $this->editingOption->modifierOptionIsAvailable = true;
        $this->editingOption->modifierOptionSortOrder = 0;
        $this->editingOption->modifierOptionTranslations = $this->emptyTranslations();
    }

    public function updateModifierOption(UpdateModifierOptionAction $updateOption): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();

        if ($this->editingModifierOptionId === null) {
            return;
        }

        $this->editingOption->modifierOptionName = $this->trimInput($this->editingOption->modifierOptionName);
        $this->refreshMutationCapabilities();
        $option = $this->findOption($this->editingModifierOptionId);
        if ($option->modifier_group_id !== $this->editingOptionGroupId) {
            throw ValidationException::withMessages(['configuration' => __('dish.errors.configuration_changed')]);
        }
        $validated = $this->editingOption->validated($this->branch, $this->canChangePrices, $this->canChangeAvailability, $option);
        $this->assertGroupInDish($this->findGroup($option->modifier_group_id));
        $updateOption->handle($this->currentUser(), $this->branch, $option, $this->optionData($validated), $this->editingOptionVersion, $this->requestIds['edit_option']);
        $this->cancelModifierOptionEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_update'));
    }

    public function deleteModifierOption(int $modifierOptionId, DeleteModifierOptionAction $deleteOption): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        $groupId = $this->displayedOptionGroups[$modifierOptionId] ?? null;
        abort_unless($groupId !== null && isset($this->deleteRequests['option_'.$modifierOptionId]), 403);
        $deleteOption->handle($this->currentUser(), $this->branch, $modifierOptionId, $this->displayedGroupVersions[$groupId] ?? -1,
            $this->deleteRequests['option_'.$modifierOptionId], $groupId);
        if ($this->editingModifierOptionId === $modifierOptionId) {
            $this->cancelModifierOptionEditing();
        }
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_remove'));
    }

    public function attachModifierGroupToItem(AssignModifierGroupToMenuItemAction $assignGroup): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        $validated = $this->assignment->validated($this->branch);
        $item = $this->findItem((int) $validated['modifierItemId']);
        $group = $this->findGroup((int) $validated['modifierItemGroupId']);
        $assignGroup->handle($this->currentUser(), $this->branch, $item, $group, $this->linksVersion, $this->assignmentGroupVersion, $this->requestIds['assign']);
        $this->linksVersion = $this->configurationQueries->item($this->branch, $item->id)->modifier_links_version;
        $this->updatedAssignmentModifierItemGroupId();
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_assigne'));
    }

    public function detachModifierGroupFromItem(
        int $itemId,
        int $modifierGroupId,
        UnassignModifierGroupFromMenuItemAction $unassignGroup,
    ): void {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        if ($this->itemId !== null && $itemId !== $this->itemId) {
            abort(404);
        }
        abort_unless(isset($this->deleteRequests['detach_'.$modifierGroupId]), 403);
        $unassignGroup->handle($this->currentUser(), $this->branch, $this->findItem($itemId), $this->findGroup($modifierGroupId),
            $this->linksVersion, $this->displayedGroupVersions[$modifierGroupId] ?? -1, $this->deleteRequests['detach_'.$modifierGroupId]);
        $this->linksVersion = $this->configurationQueries->item($this->branch, $itemId)->modifier_links_version;
        $this->changed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_unassig'));
    }

    #[On('branch-menu-updated')]
    public function refreshData(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        unset($this->groups);
    }

    /** @return LengthAwarePaginator<int, ModifierGroup> */
    #[Computed]
    public function groups(): LengthAwarePaginator
    {
        return $this->configurationQueries->groups($this->branch, $this->itemId, $this->searchTerm(), $this->optionsGroupId, $this->optionsPage);
    }

    public function updatedGroupSearch(): void
    {
        $this->resetPage('modifierGroupsPage');
        unset($this->groups);
    }

    public function nextOptionsPage(int $groupId): void
    {
        $this->changeOptionsPage($groupId, 1);
    }

    public function previousOptionsPage(int $groupId): void
    {
        $this->changeOptionsPage($groupId, -1);
    }

    private function changeOptionsPage(int $groupId, int $direction): void
    {
        $this->authorizeBranchAbility('manageMenu');
        abort_unless(isset($this->displayedGroupVersions[$groupId]), 403);
        if ($this->editingModifierOptionId !== null) {
            throw ValidationException::withMessages(['configuration' => __('dish.errors.finish_current_edit')]);
        }
        $group = $this->groups->getCollection()->firstWhere('id', $groupId);
        abort_unless($group instanceof ModifierGroup, 403);
        $this->optionsPage = max(1, min((int) ceil($group->options_count / DishConfigurationData::OPTIONS_PER_PAGE), ($this->optionsGroupId === $groupId ? $this->optionsPage : 1) + $direction));
        $this->optionsGroupId = $groupId;
        unset($this->groups);
    }

    public function render(): View
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->refreshMutationCapabilities();
        $this->assertEmbeddedSelection();
        $this->displayedGroupVersions = $this->groups->getCollection()->mapWithKeys(fn (ModifierGroup $group): array => [$group->id => $group->content_version])->all();
        $this->displayedOptionGroups = $this->groups->getCollection()->flatMap(fn (ModifierGroup $group) => $group->options)
            ->mapWithKeys(fn (ModifierOption $option): array => [$option->id => $option->modifier_group_id])->all();
        $activeRequests = [];
        foreach (array_keys($this->displayedGroupVersions) as $groupId) {
            foreach (['group_', 'detach_'] as $prefix) {
                $activeRequests[$prefix.$groupId] = $this->deleteRequests[$prefix.$groupId] ?? (string) Str::uuid();
            }
        }
        foreach (array_keys($this->displayedOptionGroups) as $optionId) {
            $activeRequests['option_'.$optionId] = $this->deleteRequests['option_'.$optionId] ?? (string) Str::uuid();
        }
        $this->deleteRequests = $activeRequests;

        return view('livewire.organizations.brands.branches.menu.modifiers', [
            'modifierGroupRows' => $this->groups->map(fn (ModifierGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'is_required' => $group->is_required,
                'min_select' => $group->min_select,
                'max_select' => $group->max_select,
                'maximum_label' => $group->max_select === 0 ? __('dish.modifiers.unlimited') : (string) $group->max_select,
                'items_count' => $group->items_count,
                'options_count' => $group->options_count,
                'options_truncated' => $group->options_count > DishConfigurationData::OPTIONS_PER_PAGE,
                'options_has_previous' => $this->optionsGroupId === $group->id && $this->optionsPage > 1,
                'options_has_next' => ($this->optionsGroupId === $group->id ? $this->optionsPage : 1) * DishConfigurationData::OPTIONS_PER_PAGE < $group->options_count,
                'is_configurable' => max($group->min_select, $group->is_required ? 1 : 0) <= $group->available_options_count
                    && ($group->max_select === 0 || $group->max_select >= max($group->min_select, $group->is_required ? 1 : 0)),
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
            'modifierGroupOptions' => $this->configurationQueries->groupOptions($this->branch, $this->searchTerm()),
            'optionGroupOptions' => $this->configurationQueries->groupOptions($this->branch, $this->searchTerm(), $this->itemId),
            'groupPagination' => $this->groups,
            'languageOptions' => SupportedLocale::labels(),
        ]);
    }

    private function searchTerm(): string
    {
        return is_string($this->groupSearch) ? mb_substr($this->groupSearch, 0, 100) : '';
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

    private function findGroup(int $id): ModifierGroup
    {
        return $this->configurationQueries->group($this->branch, $id);
    }

    private function findOption(int $id): ModifierOption
    {
        return $this->configurationQueries->option($this->branch, $id);
    }

    private function findItem(int $id): MenuItem
    {
        return $this->menuQueries->findModifierItem($this->branchId, $id);
    }

    private function firstItemId(mixed $menuId): string
    {
        return $this->menuQueries->firstItemId($this->branchId, $this->selectionValue($menuId));
    }

    private function firstModifierGroupId(): string
    {
        return $this->menuQueries->firstModifierGroupId($this->branch);
    }

    private function resetGroupForm(): void
    {
        $this->group->reset();
        $this->group->modifierGroupIsRequired = false;
        $this->group->modifierGroupMinSelect = 0;
        $this->group->modifierGroupMaxSelect = 1;
        $this->group->modifierGroupSortOrder = 0;
        $this->group->modifierGroupTranslations = $this->emptyTranslations();
    }

    private function resetOptionForm(string $groupId): void
    {
        $this->option->reset();
        $this->option->modifierOptionGroupId = $groupId;
        $this->option->modifierOptionPriceDelta = '0.00';
        $this->option->modifierOptionIsAvailable = true;
        $this->option->modifierOptionSortOrder = 0;
        $this->option->modifierOptionTranslations = $this->emptyTranslations();
        $this->updatedOptionModifierOptionGroupId();
    }

    private function assertEmbeddedSelection(): void
    {
        if ($this->itemId === null) {
            return;
        }
        $item = $this->configurationQueries->item($this->branch, $this->itemId);
        if ($this->selectionValue($this->assignment->modifierItemId) !== (string) $item->id) {
            abort(403);
        }
        $this->assignment->modifierItemMenuId = (string) $item->menu_id;
    }

    private function rejectAnotherEditor(int $currentId, int $nextId): void
    {
        if ($currentId !== $nextId) {
            throw ValidationException::withMessages(['configuration' => __('dish.errors.finish_current_edit')]);
        }
    }

    private function assertGroupInDish(ModifierGroup $group): void
    {
        if ($this->itemId !== null && ! $this->configurationQueries->isAttached($this->branch, $this->itemId, $group->id)) {
            abort(404);
        }
    }

    public function startCloningGroup(int $groupId): void
    {
        $this->authorizeBranchAbility('manageMenu');
        abort_if($this->itemId === null, 403);
        $group = $this->findGroup($groupId);
        $this->assertGroupInDish($group);
        $this->cloningGroupId = $group->id;
        $this->cloneGroupVersion = $group->content_version;
        $this->cloneLinksVersion = $this->configurationQueries->item($this->branch, $this->itemId)->modifier_links_version;
        $this->cloneName = '';
        $this->requestIds['clone'] = (string) Str::uuid();
    }

    public function cloneGroup(CloneModifierGroupForMenuItemAction $clone): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        abort_if($this->itemId === null || $this->cloningGroupId === null, 403);
        $validated = $this->validate(['cloneName' => ['required', 'string', 'max:160']]);
        $clone->handle($this->currentUser(), $this->branch, $this->findItem($this->itemId), $this->findGroup($this->cloningGroupId),
            $validated['cloneName'], $this->cloneLinksVersion, $this->cloneGroupVersion, $this->requestIds['clone']);
        $this->cloningGroupId = null;
        $this->linksVersion = $this->configurationQueries->item($this->branch, $this->itemId)->modifier_links_version;
        $this->changed();
    }

    public function cancelCloningGroup(): void
    {
        $this->cloningGroupId = null;
        $this->cloneName = '';
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
