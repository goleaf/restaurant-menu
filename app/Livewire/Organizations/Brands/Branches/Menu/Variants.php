<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Menus\CreateMenuItemVariantAction;
use App\Actions\Menus\DeleteMenuItemVariantAction;
use App\Actions\Menus\UpdateMenuItemVariantAction;
use App\Enums\MenuItemVariantType;
use App\Enums\SupportedLocale;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
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

/** @property-read EloquentCollection<int, MenuItemVariant> $variants */
final class Variants extends BranchMenuComponent
{
    private CatalogData $menuQueries;

    public string $variantMenuId = '';

    public string $variantItemId = '';

    public string $variantType = MenuItemVariantType::Portion->value;

    public string $variantName = '';

    public string $variantPrice = '0.00';

    public ?string $variantWeight = null;

    public ?string $variantVolume = null;

    public bool $variantIsDefault = false;

    public bool $variantIsAvailable = true;

    public int $variantSortOrder = 0;

    /** @var array<string, string> */
    public array $variantTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    public ?int $editingVariantId = null;

    public string $editingVariantType = MenuItemVariantType::Portion->value;

    public string $editingVariantName = '';

    public string $editingVariantPrice = '0.00';

    public ?string $editingVariantWeight = null;

    public ?string $editingVariantVolume = null;

    public bool $editingVariantIsDefault = false;

    public bool $editingVariantIsAvailable = true;

    public int $editingVariantSortOrder = 0;

    /** @var array<string, string> */
    public array $editingVariantTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

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
        $this->refreshMutationCapabilities();
        $this->variantMenuId = $this->firstMenuId();
        $this->variantItemId = $this->firstItemId($this->variantMenuId);
        $this->variantPrice = $this->selectedItemPrice();
    }

    public function updatedVariantMenuId(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->variantItemId = $this->firstItemId($this->variantMenuId);
        $this->resetCreateForm();
        $this->cancelVariantEditing();
        unset($this->variants);
    }

    public function updatedVariantItemId(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->resetCreateForm();
        $this->cancelVariantEditing();
        unset($this->variants);
    }

    public function createVariant(CreateMenuItemVariantAction $createVariant): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->refreshMutationCapabilities();
        $validated = $this->validate($this->variantRules());

        $createVariant->handle(
            actor: $this->currentUser(),
            branch: $this->branch,
            item: $this->findItem((int) $validated['variantItemId']),
            data: $this->variantData($validated),
        );

        $this->resetCreateForm();
        $this->changed();
        Flux::toast(variant: 'success', text: __('menu.variants.admin.created'));
    }

    public function startEditingVariant(int $variantId): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $variant = $this->findVariant($variantId);
        $this->editingVariantId = $variant->id;
        $this->editingVariantType = $variant->type->value;
        $this->editingVariantName = $variant->name;
        $this->editingVariantPrice = MoneyFormatter::centsToDecimal($variant->price_cents);
        $this->editingVariantWeight = $variant->weight;
        $this->editingVariantVolume = $variant->volume;
        $this->editingVariantIsDefault = $variant->is_default;
        $this->editingVariantIsAvailable = $variant->is_available;
        $this->editingVariantSortOrder = $variant->sort_order;
        $this->editingVariantTranslations = $this->translationValues($variant->translations);
    }

    public function cancelVariantEditing(): void
    {
        $this->reset('editingVariantId', 'editingVariantName');
        $this->editingVariantType = MenuItemVariantType::Portion->value;
        $this->editingVariantPrice = '0.00';
        $this->editingVariantWeight = null;
        $this->editingVariantVolume = null;
        $this->editingVariantIsDefault = false;
        $this->editingVariantIsAvailable = true;
        $this->editingVariantSortOrder = 0;
        $this->editingVariantTranslations = $this->emptyTranslations();
    }

    public function updateVariant(UpdateMenuItemVariantAction $updateVariant): void
    {
        $this->authorizeBranchAbility('manageMenu');

        if ($this->editingVariantId === null) {
            return;
        }

        $this->refreshMutationCapabilities();
        $variant = $this->findVariant($this->editingVariantId);
        $rules = RestaurantValidationRules::menuItemVariant(
            prefix: 'editing',
            canChangePrices: $this->canChangePrices,
            canChangeAvailability: $this->canChangeAvailability,
        );
        $rules['editingVariantName'][] = $this->variantNameUniqueRule(
            itemId: $variant->menu_item_id,
            type: $this->editingVariantType,
            ignoreVariantId: $this->editingVariantId,
        );
        $validated = $this->validate($rules);

        $updateVariant->handle(
            actor: $this->currentUser(),
            branch: $this->branch,
            variant: $variant,
            data: $this->variantData($validated, 'editing'),
        );

        $this->cancelVariantEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('menu.variants.admin.updated'));
    }

    public function deleteVariant(int $variantId, DeleteMenuItemVariantAction $deleteVariant): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $deleteVariant->handle($this->currentUser(), $this->branch, $this->findVariant($variantId));
        $this->cancelVariantEditing();
        $this->changed();
        Flux::toast(variant: 'success', text: __('menu.variants.admin.deleted'));
    }

    #[On('branch-menu-updated')]
    public function refreshData(): void
    {
        $this->authorizeBranchAbility('manageMenu');

        if (! $this->selectionExists()) {
            $this->variantMenuId = $this->firstMenuId();
            $this->variantItemId = $this->firstItemId($this->variantMenuId);
        }

        unset($this->variants);
    }

    /** @return EloquentCollection<int, MenuItemVariant> */
    #[Computed]
    public function variants(): EloquentCollection
    {
        return $this->menuQueries->variants($this->branchId, $this->variantItemId);
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.menu.variants', [
            'menuOptions' => $this->menuOptions(),
            'itemOptions' => $this->itemOptions(),
            'variantTypeOptions' => MenuItemVariantType::options(),
            'languageOptions' => SupportedLocale::labels(),
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

    /** @return array<string, list<mixed>> */
    private function variantRules(): array
    {
        $rules = [
            'variantMenuId' => ['required', 'integer', $this->menuRule()],
            'variantItemId' => ['required', 'integer', $this->itemRule($this->variantMenuId)],
            ...RestaurantValidationRules::menuItemVariant(
                canChangePrices: $this->canChangePrices,
                canChangeAvailability: $this->canChangeAvailability,
            ),
        ];

        $rules['variantName'][] = $this->variantNameUniqueRule(
            itemId: (int) $this->variantItemId,
            type: $this->variantType,
        );

        return $rules;
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
        return $this->menuQueries->itemOptions($this->branchId, $this->variantMenuId);
    }

    private function menuRule(): mixed
    {
        return Rule::exists((new Menu)->getTable(), 'id')
            ->where(fn ($query) => $query->where('branch_id', $this->branchId));
    }

    private function itemRule(string $menuId): mixed
    {
        return Rule::exists((new MenuItem)->getTable(), 'id')
            ->where(fn ($query) => $query->where('menu_id', (int) $menuId));
    }

    private function variantNameUniqueRule(int $itemId, string $type, ?int $ignoreVariantId = null): mixed
    {
        $rule = Rule::unique((new MenuItemVariant)->getTable(), 'name')
            ->where(fn ($query) => $query
                ->where('menu_item_id', $itemId)
                ->where('type', $type));

        return $ignoreVariantId === null ? $rule : $rule->ignore($ignoreVariantId);
    }

    private function findItem(int $itemId): MenuItem
    {
        return $this->menuQueries->findVariantItem($this->branchId, $itemId);
    }

    private function findVariant(int $variantId): MenuItemVariant
    {
        return $this->menuQueries->findVariant($this->branchId, $variantId);
    }

    private function firstMenuId(): string
    {
        return $this->menuQueries->firstMenuId($this->branch);
    }

    private function firstItemId(string $menuId): string
    {
        return $this->menuQueries->firstItemId($this->branchId, $menuId);
    }

    private function selectedItemPrice(): string
    {
        return $this->menuQueries->selectedItemPrice($this->branchId, $this->variantItemId);
    }

    private function selectionExists(): bool
    {
        return $this->menuQueries->selectionExists($this->branchId, $this->variantMenuId, $this->variantItemId);
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
        $this->variantType = MenuItemVariantType::Portion->value;
        $this->variantName = '';
        $this->variantPrice = $this->selectedItemPrice();
        $this->variantWeight = null;
        $this->variantVolume = null;
        $this->variantIsDefault = false;
        $this->variantIsAvailable = true;
        $this->variantSortOrder = 0;
        $this->variantTranslations = $this->emptyTranslations();
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
