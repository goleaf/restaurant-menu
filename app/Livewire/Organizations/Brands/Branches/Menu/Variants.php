<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Menus\CreateMenuItemVariantAction;
use App\Actions\Menus\DeleteMenuItemVariantAction;
use App\Actions\Menus\UpdateMenuItemVariantAction;
use App\Enums\MenuItemVariantType;
use App\Enums\MenuOperationKind;
use App\Enums\SupportedLocale;
use App\Livewire\Forms\Menus\MenuVariantForm;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use App\Services\Menus\CatalogData;
use App\Services\Menus\DishConfigurationData;
use App\Support\MoneyFormatter;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\WithPagination;

/** @property-read LengthAwarePaginator<int, MenuItemVariant> $variants */
final class Variants extends BranchMenuComponent
{
    use WithPagination;

    private CatalogData $menuQueries;

    private DishConfigurationData $configurationQueries;

    #[Locked]
    public ?int $itemId = null;

    #[Locked]
    public int $createVersion = 0;

    #[Locked]
    public int $editingVersion = 0;

    #[Locked]
    public int $displayedVersion = 0;

    #[Locked]
    public array $deleteRequests = [];

    #[Locked]
    public string $createRequestId = '';

    #[Locked]
    public string $editingRequestId = '';

    public MenuVariantForm $variant;

    public MenuVariantForm $editingVariant;

    public mixed $variantMenuId = '';

    public mixed $variantItemId = '';

    public ?int $editingVariantId = null;

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
        $this->refreshMutationCapabilities();
        $this->itemId = $itemId;
        if ($itemId !== null) {
            $item = $this->configurationQueries->item($this->branch, $itemId);
            $this->variantMenuId = (string) $item->menu_id;
            $this->variantItemId = (string) $item->id;
        } else {
            $this->variantMenuId = $this->firstMenuId();
            $this->variantItemId = $this->firstItemId($this->variantMenuId);
        }
        $this->variant->variantPrice = $this->selectedItemPrice();
        $this->resetCreateVersion();
    }

    public function updatedVariantMenuId(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        $this->variantItemId = $this->firstItemId($this->variantMenuId);
        $this->resetCreateForm();
        $this->cancelVariantEditing();
        unset($this->variants);
    }

    public function updatedVariantItemId(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        $this->resetCreateForm();
        $this->cancelVariantEditing();
        unset($this->variants);
    }

    public function createVariant(CreateMenuItemVariantAction $createVariant): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        $this->refreshMutationCapabilities();
        $this->validate(['variantMenuId' => ['bail', 'required', 'numeric', 'integer', $this->menuRule()],
            'variantItemId' => ['bail', 'required', 'numeric', 'integer', $this->itemRule($this->variantMenuId)]]);
        $validated = $this->variant->validated((int) $this->variantItemId, $this->canChangePrices, $this->canChangeAvailability,
            $this->configurationQueries->replayedResultId($this->currentUser(), $this->branch, $this->createRequestId, MenuOperationKind::VariantChange));

        $createVariant->handle(
            actor: $this->currentUser(),
            branch: $this->branch,
            item: $this->findItem((int) $this->variantItemId),
            data: $this->variantData($validated),
            expectedVersion: $this->createVersion,
            requestId: $this->createRequestId,
        );

        $this->resetCreateForm();
        $this->changed();
        Flux::toast(variant: 'success', text: __('menu.variants.admin.created'));
    }

    public function startEditingVariant(int $variantId): void
    {
        $this->authorizeBranchAbility('manageMenu');
        if ($this->editingVariantId !== null) {
            if ($this->editingVariantId !== $variantId) {
                throw ValidationException::withMessages(['configuration' => __('dish.errors.finish_current_edit')]);
            }

            return;
        }
        $variant = $this->findVariant($variantId);
        $this->editingVersion = $this->configurationQueries->item($this->branch, $variant->menu_item_id)->variants_version;
        $this->editingRequestId = (string) Str::uuid();
        $this->editingVariantId = $variant->id;
        $this->editingVariant->variantType = $variant->type->value;
        $this->editingVariant->variantName = $variant->name;
        $this->editingVariant->variantPrice = MoneyFormatter::centsToDecimal($variant->price_cents);
        $this->editingVariant->variantWeight = $variant->weight;
        $this->editingVariant->variantVolume = $variant->volume;
        $this->editingVariant->variantIsDefault = $variant->is_default;
        $this->editingVariant->variantIsAvailable = $variant->is_available;
        $this->editingVariant->variantSortOrder = $variant->sort_order;
        $this->editingVariant->variantTranslations = $this->translationValues($variant->translations);
    }

    public function cancelVariantEditing(): void
    {
        $this->editingVariantId = null;
        $this->editingVariant->reset();
        $this->editingVariant->variantType = MenuItemVariantType::Portion->value;
        $this->editingVariant->variantPrice = '0.00';
        $this->editingVariant->variantWeight = null;
        $this->editingVariant->variantVolume = null;
        $this->editingVariant->variantIsDefault = false;
        $this->editingVariant->variantIsAvailable = true;
        $this->editingVariant->variantSortOrder = 0;
        $this->editingVariant->variantTranslations = $this->emptyTranslations();
    }

    public function updateVariant(UpdateMenuItemVariantAction $updateVariant): void
    {
        $this->authorizeBranchAbility('manageMenu');

        if ($this->editingVariantId === null) {
            return;
        }

        $this->refreshMutationCapabilities();
        $variant = $this->findVariant($this->editingVariantId);
        $validated = $this->editingVariant->validated($variant->menu_item_id, $this->canChangePrices, $this->canChangeAvailability, $variant->id);

        $updateVariant->handle(
            actor: $this->currentUser(),
            branch: $this->branch,
            variant: $variant,
            data: $this->variantData($validated),
            expectedVersion: $this->editingVersion,
            requestId: $this->editingRequestId,
        );

        $this->cancelVariantEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('menu.variants.admin.updated'));
    }

    public function deleteVariant(int $variantId, DeleteMenuItemVariantAction $deleteVariant): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->assertEmbeddedSelection();
        abort_unless(isset($this->deleteRequests[$variantId]), 403);
        $deleteVariant->handle($this->currentUser(), $this->branch, $variantId, $this->displayedVersion, $this->deleteRequests[$variantId], (int) $this->variantItemId);
        if ($this->editingVariantId === $variantId) {
            $this->cancelVariantEditing();
        }
        $this->changed();
        Flux::toast(variant: 'success', text: __('menu.variants.admin.deleted'));
    }

    #[On('branch-menu-updated')]
    public function refreshData(): void
    {
        $this->authorizeBranchAbility('manageMenu');

        if (! $this->selectionExists()) {
            if ($this->itemId !== null) {
                abort(404);
            }
            $this->variantMenuId = $this->firstMenuId();
            $this->variantItemId = $this->firstItemId($this->variantMenuId);
        }

        unset($this->variants);
    }

    /** @return LengthAwarePaginator<int, MenuItemVariant> */
    #[Computed]
    public function variants(): LengthAwarePaginator
    {
        return $this->configurationQueries->variants($this->branch, (int) $this->selectionValue($this->variantItemId));
    }

    public function render(): View
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->refreshMutationCapabilities();
        $this->assertEmbeddedSelection();
        if ($this->selectionExists()) {
            $this->displayedVersion = $this->configurationQueries->item($this->branch, (int) $this->variantItemId)->variants_version;
        }
        $this->deleteRequests = $this->variants->getCollection()->mapWithKeys(fn (MenuItemVariant $variant): array => [
            $variant->id => $this->deleteRequests[$variant->id] ?? (string) Str::uuid(),
        ])->all();

        return view('livewire.organizations.brands.branches.menu.variants', [
            'menuOptions' => $this->itemId === null ? $this->menuOptions() : [],
            'itemOptions' => $this->itemId === null ? $this->itemOptions() : [],
            'variantTypeOptions' => MenuItemVariantType::options(),
            'languageOptions' => SupportedLocale::labels(),
            'variantPagination' => $this->variants,
            'variantRows' => $this->variants->map(fn (MenuItemVariant $variant): array => [
                'id' => $variant->id,
                'type' => $variant->type->label(),
                'name' => $variant->name,
                'formatted_price' => MoneyFormatter::formatCents($variant->price_cents, $this->branch->currency),
                'weight' => $variant->weight,
                'volume' => $variant->volume,
                'is_default' => $variant->is_default,
                'is_available' => $variant->is_available,
                'sort_order' => $variant->sort_order,
                'translations' => $this->translationValues($variant->translations),
            ])->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function variantData(array $validated, string $prefix = ''): array
    {
        $field = static fn (string $name): string => $prefix === '' ? $name : $prefix.ucfirst($name);
        $data = [
            'type' => (string) $validated[$field('variantType')],
            'name' => (string) $validated[$field('variantName')],
            'weight' => $validated[$field('variantWeight')] ?? null,
            'volume' => $validated[$field('variantVolume')] ?? null,
            'is_default' => (bool) $validated[$field('variantIsDefault')],
            'sort_order' => (int) $validated[$field('variantSortOrder')],
            'translations' => $validated[$field('variantTranslations')] ?? [],
        ];

        if ($this->canChangePrices) {
            $data['price'] = $validated[$field('variantPrice')];
        }

        if ($this->canChangeAvailability) {
            $data['is_available'] = (bool) $validated[$field('variantIsAvailable')];
        }

        return $data;
    }

    /** @return list<array{value: string, label: string}> */
    private function menuOptions(): array
    {
        return $this->menuQueries->menuOptions($this->branch);
    }

    /** @return list<array{value: string, label: string}> */
    private function itemOptions(): array
    {
        return $this->menuQueries->itemOptions($this->branchId, $this->selectionValue($this->variantMenuId));
    }

    private function menuRule(): mixed
    {
        return Rule::exists((new Menu)->getTable(), 'id')
            ->where(fn ($query) => $query->where('branch_id', $this->branchId));
    }

    private function itemRule(mixed $menuId): mixed
    {
        return Rule::exists((new MenuItem)->getTable(), 'id')
            ->where(fn ($query) => $query->where('menu_id', (int) $this->selectionValue($menuId)));
    }

    private function findItem(int $itemId): MenuItem
    {
        return $this->menuQueries->findVariantItem($this->branchId, $itemId);
    }

    private function findVariant(int $variantId): MenuItemVariant
    {
        $variant = $this->menuQueries->findVariant($this->branchId, $variantId);
        if ($this->itemId !== null && $variant->menu_item_id !== $this->itemId) {
            abort(404);
        }

        return $variant;
    }

    private function firstMenuId(): string
    {
        return $this->menuQueries->firstMenuId($this->branch);
    }

    private function firstItemId(mixed $menuId): string
    {
        return $this->menuQueries->firstItemId($this->branchId, $this->selectionValue($menuId));
    }

    private function selectedItemPrice(): string
    {
        return $this->menuQueries->selectedItemPrice($this->branchId, $this->selectionValue($this->variantItemId));
    }

    private function selectionExists(): bool
    {
        return $this->menuQueries->selectionExists($this->branchId, $this->selectionValue($this->variantMenuId), $this->selectionValue($this->variantItemId));
    }

    /**
     * @param  EloquentCollection<int, MenuItemVariantTranslation>  $translations
     * @return array<string, string>
     */
    private function translationValues(EloquentCollection $translations): array
    {
        $values = $this->emptyTranslations();

        foreach ($translations as $translation) {
            if (array_key_exists($translation->language_code, $values)) {
                $values[$translation->language_code] = $translation->name;
            }
        }

        return $values;
    }

    /** @return array<string, string> */
    private function emptyTranslations(): array
    {
        return array_fill_keys(SupportedLocale::values(), '');
    }

    private function resetCreateForm(): void
    {
        $this->variant->variantType = MenuItemVariantType::Portion->value;
        $this->variant->variantName = '';
        $this->variant->variantPrice = $this->selectedItemPrice();
        $this->variant->variantWeight = null;
        $this->variant->variantVolume = null;
        $this->variant->variantIsDefault = false;
        $this->variant->variantIsAvailable = true;
        $this->variant->variantSortOrder = 0;
        $this->variant->variantTranslations = $this->emptyTranslations();
        $this->resetCreateVersion();
    }

    private function resetCreateVersion(): void
    {
        $this->createVersion = $this->selectionExists()
            ? $this->configurationQueries->item($this->branch, (int) $this->variantItemId)->variants_version : 0;
        $this->createRequestId = (string) Str::uuid();
    }

    private function assertEmbeddedSelection(): void
    {
        if ($this->itemId === null) {
            return;
        }
        $item = $this->configurationQueries->item($this->branch, $this->itemId);
        if ((string) $item->id !== $this->selectionValue($this->variantItemId)
            || (string) $item->menu_id !== $this->selectionValue($this->variantMenuId)) {
            abort(403);
        }
    }

    private function changed(): void
    {
        unset($this->variants);
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
