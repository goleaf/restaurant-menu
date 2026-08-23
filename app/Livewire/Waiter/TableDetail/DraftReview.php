<?php

declare(strict_types=1);

namespace App\Livewire\Waiter\TableDetail;

use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\Waiter\AddManualWaiterOrderItemAction;
use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\DeleteDraftOrderItemByWaiterAction;
use App\Actions\Waiter\EnsureWaiterCanEditDraftOrderAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
use App\Actions\Waiter\ReturnRejectedDraftOrderToDraftAction;
use App\Actions\Waiter\UpdateDraftOrderItemByWaiterAction;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSessionGuest;
use App\Services\Waiter\TableDetailChangeDetector;
use App\Services\Waiter\WaiterTableQueryService;
use App\Support\MoneyFormatter;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

final class DraftReview extends TableDetailSection
{
    private BuildDraftOrderItemModifierSnapshots $buildModifierSnapshots;

    #[Locked]
    public string $changeFingerprint = '';

    /** @var array<string, mixed> */
    public array $draftReview = [];

    public string $rejectionReason = '';

    public string $reviewFeedbackMessage = '';

    /** @var list<array{value: string, label: string, price: string}> */
    public array $addableMenuItems = [];

    public ?int $addableMenuItemsBranchId = null;

    public string $addingGuestId = '';

    public string $manualGuestName = '';

    public string $addingMenuItemId = '';

    public int $addingQuantity = 1;

    public string $addingItemName = '';

    public string $addingUnitPrice = '0.00';

    public string $addingItemVariantId = '';

    /** @var list<array{id: int, name: string, price_cents: int, formatted_price: string, is_default: bool}> */
    public array $addingVariants = [];

    public string $addingItemTotal = '0.00';

    public string $addingComment = '';

    /** @var array<int, list<int>> */
    public array $addingModifierOptions = [];

    /** @var list<array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta_cents: int, formatted_price_delta: string}>}> */
    public array $addingModifierGroups = [];

    public ?int $editingItemId = null;

    public string $editingItemName = '';

    public int $editingQuantity = 1;

    public string $editingUnitPrice = '0.00';

    public string $editingItemVariantId = '';

    /** @var list<array{id: int, name: string, price_cents: int, formatted_price: string, is_default: bool}> */
    public array $editingVariants = [];

    public string $editingModifierTotal = '0.00';

    public string $editingItemTotal = '0.00';

    public string $editingComment = '';

    /** @var array<int, list<int>> */
    public array $editingModifierOptions = [];

    /** @var list<array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta_cents: int, formatted_price_delta: string}>}> */
    public array $editingModifierGroups = [];

    public function boot(
        BuildWaiterTableDetailAction $buildWaiterTableDetail,
        TableDetailChangeDetector $changeDetector,
        WaiterTableQueryService $waiterQueries,
        BuildDraftOrderItemModifierSnapshots $buildModifierSnapshots,
    ): void {
        parent::boot(
            $buildWaiterTableDetail,
            $changeDetector,
            $waiterQueries,
            $buildModifierSnapshots,
        );
        $this->buildModifierSnapshots = $buildModifierSnapshots;
    }

    /** @param array<string, mixed> $initialDraftReview */
    public function mount(int $tableSessionId, array $initialDraftReview = []): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->authorizeViewableTableSession();
        $this->draftReview = $initialDraftReview === []
            ? $this->draftReviewPayload($this->freshViewableTablePayload())
            : $initialDraftReview;
        $this->changeFingerprint = $this->changeDetector->draftReviewFingerprint($this->tableSessionId);
        $this->syncAddableMenuItems();
    }

    public function refreshDraftReview(): void
    {
        $this->authorizeViewableTableSession();
        $currentFingerprint = $this->changeDetector->draftReviewFingerprint($this->tableSessionId);

        if ($this->changeFingerprint !== '' && hash_equals($this->changeFingerprint, $currentFingerprint)) {
            return;
        }

        $this->draftReview = $this->draftReviewPayload($this->freshViewableTablePayload());
        $this->changeFingerprint = $this->changeDetector->draftReviewFingerprint($this->tableSessionId);
        $this->syncAddableMenuItems();
    }

    public function updatedAddingMenuItemId(): void
    {
        $this->authorizeWaiterTableSession();
        $this->prepareAddingMenuItem();
    }

    public function updatedAddingQuantity(): void
    {
        $this->authorizeWaiterTableSession();
        $this->refreshAddingItemTotal();
    }

    public function updatedAddingItemVariantId(): void
    {
        $this->authorizeWaiterTableSession();
        $this->addingUnitPrice = $this->selectedVariantPrice($this->addingVariants, $this->addingItemVariantId, $this->addingUnitPrice);
        $this->refreshAddingItemTotal();
    }

    public function updatedEditingQuantity(): void
    {
        $this->authorizeWaiterTableSession();
        $this->refreshEditingItemTotal();
    }

    public function updatedEditingItemVariantId(): void
    {
        $this->authorizeWaiterTableSession();
        $this->editingUnitPrice = $this->selectedVariantPrice($this->editingVariants, $this->editingItemVariantId, $this->editingUnitPrice);
        $this->refreshEditingItemTotal();
    }

    public function toggleAddingModifierOption(int $modifierGroupId, int $modifierOptionId): void
    {
        $this->authorizeWaiterTableSession();
        $this->addingModifierOptions = $this->toggledModifierOptions(
            $this->addingModifierGroups,
            $this->addingModifierOptions,
            $modifierGroupId,
            $modifierOptionId,
        );
        $this->refreshAddingItemTotal();
    }

    public function toggleEditingModifierOption(int $modifierGroupId, int $modifierOptionId): void
    {
        $this->authorizeWaiterTableSession();
        $this->editingModifierOptions = $this->toggledModifierOptions(
            $this->editingModifierGroups,
            $this->editingModifierOptions,
            $modifierGroupId,
            $modifierOptionId,
        );
        $this->refreshEditingItemTotal();
    }

    public function addDraftItem(AddManualWaiterOrderItemAction $addManualWaiterOrderItem): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';
        $tableSession = $this->authorizeWaiterTableSession();
        $serverDraftReview = $this->draftReviewPayload($this->freshWaiterTablePayload());

        if (! data_get($serverDraftReview, 'manual_order.can_add')) {
            $this->addError('draft_edit', __('ui.actions.waiter.addmanualwaiterorderitemaction.u_vas_net_prava_vrucnuiu_d'));

            return;
        }

        $validated = $this->validate($this->addingDraftItemRules());
        $this->addingQuantity = (int) $validated['addingQuantity'];
        $this->addingComment = (string) ($validated['addingComment'] ?? '');
        $this->addingModifierOptions = $validated['addingModifierOptions'] ?? [];
        $guest = $this->selectedAddingGuest();
        $menuItem = $this->selectedAddingMenuItem();

        if (! $menuItem instanceof MenuItem) {
            $this->addError('addingMenuItemId', __('ui.livewire.waiter.tabledetail.vyberite_bliudo_pered_dobavleniem_pozicii'));

            return;
        }

        try {
            $draftOrderItem = $addManualWaiterOrderItem->handle(
                tableSession: $tableSession,
                waiter: $this->currentUser(),
                guest: $guest,
                guestName: $this->manualGuestName,
                menuItem: $menuItem,
                quantity: $this->addingQuantity,
                selectedModifierOptions: $this->addingModifierOptions,
                menuItemVariantId: $this->nullableId($this->addingItemVariantId),
                comment: $this->addingComment,
                itemName: $this->addingItemName,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('ui.livewire.waiter.tabledetail.poziciia_dobavlena_gosti_uvidiat_obnovlennyi');
        $this->addingGuestId = (string) $draftOrderItem->table_session_guest_id;
        $this->manualGuestName = '';
        $this->resetAddingForm();
        $this->refreshAndNotify();
    }

    public function editDraftItem(int $itemId, EnsureWaiterCanEditDraftOrderAction $ensureWaiterCanEditDraftOrder): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $serverDraftReview = $this->draftReviewPayload($this->freshWaiterTablePayload());

        if (! data_get($serverDraftReview, 'draft.can_edit')) {
            $this->addError('draft_edit', __('ui.livewire.waiter.tabledetail.u_vas_net_prava_redaktirovat_etot_cernovik'));

            return;
        }

        $draftOrderItem = $this->draftOrderItemForCurrentTable($itemId);

        if (! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_edit', __('ui.livewire.waiter.tabledetail.poziciia_ne_naidena_v_etom_cernovike'));

            return;
        }

        try {
            $ensureWaiterCanEditDraftOrder->handle($draftOrderItem->draftOrder, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->editingItemId = $draftOrderItem->id;
        $this->editingItemName = $draftOrderItem->item_name;
        $this->editingQuantity = max(1, min(99, (int) $draftOrderItem->quantity));
        $this->editingUnitPrice = MoneyFormatter::centsToDecimal($draftOrderItem->unit_price_cents);
        $this->editingItemVariantId = $draftOrderItem->menu_item_variant_id === null ? '' : (string) $draftOrderItem->menu_item_variant_id;
        $this->editingVariants = $this->variantPayloadFor($draftOrderItem->menuItem);
        $this->editingModifierTotal = MoneyFormatter::centsToDecimal($draftOrderItem->modifier_total_cents);
        $this->editingComment = (string) $draftOrderItem->comment;
        $this->editingModifierGroups = $this->modifierGroupPayloadFor($draftOrderItem->menuItem);
        $this->editingModifierOptions = $this->modifierOptionsFromSnapshots($draftOrderItem->selected_modifiers, $this->editingModifierGroups);
        $this->refreshEditingItemTotal();
    }

    public function closeEditDraftItem(): void
    {
        $this->authorizeWaiterTableSession();
        $this->resetValidation();
        $this->editingItemId = null;
        $this->editingItemName = '';
        $this->editingQuantity = 1;
        $this->editingUnitPrice = '0.00';
        $this->editingItemVariantId = '';
        $this->editingVariants = [];
        $this->editingModifierTotal = '0.00';
        $this->editingItemTotal = '0.00';
        $this->editingComment = '';
        $this->editingModifierOptions = [];
        $this->editingModifierGroups = [];
    }

    public function updateDraftItem(UpdateDraftOrderItemByWaiterAction $updateDraftOrderItem): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';
        $this->authorizeWaiterTableSession();

        if ($this->editingItemId === null) {
            return;
        }

        $validated = $this->validate($this->editingDraftItemRules());
        $this->editingQuantity = (int) $validated['editingQuantity'];
        $this->editingComment = (string) ($validated['editingComment'] ?? '');
        $this->editingModifierOptions = $validated['editingModifierOptions'] ?? [];
        $draftOrderItem = $this->draftOrderItemForCurrentTable($this->editingItemId);

        if (! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_edit', __('ui.livewire.waiter.tabledetail.poziciia_ne_naidena'));

            return;
        }

        try {
            $updateDraftOrderItem->handle(
                draftOrderItem: $draftOrderItem,
                editedBy: $this->currentUser(),
                quantity: $this->editingQuantity,
                selectedModifierOptions: $this->editingModifierOptions,
                menuItemVariantId: $this->nullableId($this->editingItemVariantId),
                comment: $this->editingComment,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('ui.livewire.waiter.tabledetail.poziciia_obnovlena_gosti_uvidiat_aktualnuiu');
        $this->closeEditDraftItem();
        $this->refreshAndNotify();
    }

    public function deleteDraftItem(int $itemId, DeleteDraftOrderItemByWaiterAction $deleteDraftOrderItem): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $draftOrderItem = $this->draftOrderItemForCurrentTable($itemId);

        if (! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_edit', __('ui.livewire.waiter.tabledetail.poziciia_ne_naidena'));

            return;
        }

        try {
            $deleteDraftOrderItem->handle($draftOrderItem, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        if ($this->editingItemId === $itemId) {
            $this->closeEditDraftItem();
        }

        $this->reviewFeedbackMessage = __('ui.livewire.waiter.tabledetail.poziciia_udalena_gosti_uvidiat_obnovlennyi_c');
        $this->refreshAndNotify();
    }

    public function confirmDraft(ConfirmDraftOrderByWaiterAction $confirmDraftOrder): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('ui.livewire.waiter.tabledetail.u_etogo_stola_net_cernovika_dlia_podtverzden'));

            return;
        }

        try {
            $confirmDraftOrder->handle($draftOrder, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->rejectionReason = '';
        $this->reviewFeedbackMessage = __('ui.livewire.waiter.tabledetail.zakaz_podtverzden_oficiantom_kuxnia_i_bar_po');
        $this->refreshAndNotify();
    }

    public function rejectDraft(RejectDraftOrderByWaiterAction $rejectDraftOrder): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('ui.livewire.waiter.tabledetail.u_etogo_stola_net_cernovika_dlia_otkloneniia'));

            return;
        }

        $validated = $this->validate(RestaurantValidationRules::waiterRejectionReason('rejectionReason'), [
            'rejectionReason.required' => __('ui.actions.waiter.rejectdraftorderbywaiteraction.ukazite_pricinu_otklonenii'),
            'rejectionReason.min' => __('ui.livewire.waiter.tabledetail.pricina_otkloneniia_dolzna_byt_poniatnoi_dli'),
        ]);

        try {
            $rejectDraftOrder->handle($draftOrder, $this->currentUser(), (string) $validated['rejectionReason']);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('ui.livewire.waiter.tabledetail.cernovik_otklonen_gosti_uvidiat_pricinu');
        $this->refreshAndNotify();
    }

    public function returnRejectedDraftToDraft(ReturnRejectedDraftOrderToDraftAction $returnDraft): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('ui.livewire.waiter.tabledetail.u_etogo_stola_net_cernovika_dlia_vozvrata'));

            return;
        }

        try {
            $returnDraft->handle($draftOrder, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->rejectionReason = '';
        $this->reviewFeedbackMessage = __('ui.livewire.waiter.tabledetail.cernovik_vozvrashhen_gostiam_dlia_pravok');
        $this->refreshAndNotify();
    }

    public function render(): View
    {
        return view('livewire.waiter.table-detail.draft-review');
    }

    private function refreshAndNotify(): void
    {
        $this->refreshDraftReview();
        $this->dispatch('waiter-table-updated');
    }

    private function currentDraftOrder(): ?DraftOrder
    {
        return $this->waiterQueries->currentDraftOrder($this->tableSessionId);
    }

    /** @return array<string, list<mixed>> */
    private function addingDraftItemRules(): array
    {
        return [
            ...RestaurantValidationRules::quantity('addingQuantity'),
            ...RestaurantValidationRules::guestComment('addingComment'),
            ...RestaurantValidationRules::selectedModifierOptions('addingModifierOptions'),
            'addingItemVariantId' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, list<mixed>> */
    private function editingDraftItemRules(): array
    {
        return [
            ...RestaurantValidationRules::quantity('editingQuantity'),
            ...RestaurantValidationRules::guestComment('editingComment'),
            ...RestaurantValidationRules::selectedModifierOptions('editingModifierOptions'),
            'editingItemVariantId' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function selectedAddingGuest(): ?TableSessionGuest
    {
        $guestId = (int) $this->addingGuestId;

        if ($guestId < 1) {
            return null;
        }

        return $this->waiterQueries->guestForTable($guestId, $this->tableSessionId);
    }

    private function selectedAddingMenuItem(): ?MenuItem
    {
        return $this->configuredMenuItem((int) $this->addingMenuItemId);
    }

    private function draftOrderItemForCurrentTable(int $itemId): ?DraftOrderItem
    {
        return $this->waiterQueries->draftOrderItemForTable($itemId, $this->tableSessionId);
    }

    /** @return list<array{value: string, label: string, price: string}> */
    private function menuItemOptionsForCurrentBranch(): array
    {
        $branchId = (int) data_get($this->draftReview, 'branch.id');

        return $this->waiterQueries->menuItemOptionsForBranch($branchId);
    }

    private function syncAddableMenuItems(): void
    {
        $canAdd = (bool) data_get($this->draftReview, 'manual_order.can_add');
        $branchId = (int) data_get($this->draftReview, 'branch.id');

        if (! $canAdd || $branchId < 1) {
            $this->addableMenuItems = [];
            $this->addableMenuItemsBranchId = null;

            return;
        }

        if ($this->addableMenuItemsBranchId === $branchId) {
            return;
        }

        $this->addableMenuItems = $this->menuItemOptionsForCurrentBranch();
        $this->addableMenuItemsBranchId = $branchId;
    }

    private function prepareAddingMenuItem(): void
    {
        $this->addingItemName = '';
        $this->addingUnitPrice = '0.00';
        $this->addingItemTotal = '0.00';
        $this->addingItemVariantId = '';
        $this->addingVariants = [];
        $this->addingModifierOptions = [];
        $this->addingModifierGroups = [];
        $menuItem = $this->configuredMenuItem((int) $this->addingMenuItemId);

        if (! $menuItem instanceof MenuItem) {
            return;
        }

        $this->addingItemName = $menuItem->name;
        $this->addingUnitPrice = MoneyFormatter::centsToDecimal($menuItem->price_cents);
        $this->addingVariants = $this->variantPayloadFor($menuItem);
        $defaultVariant = collect($this->addingVariants)->firstWhere('is_default', true) ?? collect($this->addingVariants)->first();
        $this->addingItemVariantId = is_array($defaultVariant) ? (string) $defaultVariant['id'] : '';
        $this->addingUnitPrice = $this->selectedVariantPrice($this->addingVariants, $this->addingItemVariantId, $this->addingUnitPrice);
        $this->addingModifierGroups = $this->modifierGroupPayloadFor($menuItem);

        foreach ($this->addingModifierGroups as $modifierGroup) {
            $this->addingModifierOptions[$modifierGroup['id']] = [];
        }

        $this->refreshAddingItemTotal();
    }

    private function configuredMenuItem(int $menuItemId): ?MenuItem
    {
        $allowedMenuItemIds = collect($this->addableMenuItems)
            ->pluck('value')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        return $this->waiterQueries->configuredMenuItem($menuItemId, $allowedMenuItemIds);
    }

    /** @return list<array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta_cents: int, formatted_price_delta: string}>}> */
    private function modifierGroupPayloadFor(?MenuItem $menuItem): array
    {
        if (! $menuItem instanceof MenuItem) {
            return [];
        }

        return $this->buildModifierSnapshots
            ->groupsFor($menuItem)
            ->map(fn ($modifierGroup): array => [
                'id' => $modifierGroup->id,
                'name' => $modifierGroup->name,
                'is_required' => (bool) $modifierGroup->is_required,
                'min_select' => (int) $modifierGroup->min_select,
                'max_select' => (int) $modifierGroup->max_select,
                'options' => $modifierGroup->options
                    ->map(fn ($modifierOption): array => [
                        'id' => $modifierOption->id,
                        'name' => $modifierOption->name,
                        'price_delta_cents' => $modifierOption->price_delta_cents,
                        'formatted_price_delta' => MoneyFormatter::formatSignedCents(
                            $modifierOption->price_delta_cents,
                            (string) data_get($this->draftReview, 'branch.currency', 'EUR'),
                        ),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, price_cents: int, formatted_price: string, is_default: bool}>
     */
    private function variantPayloadFor(?MenuItem $menuItem): array
    {
        if (! $menuItem instanceof MenuItem) {
            return [];
        }

        return $this->waiterQueries->availableVariants(
            $menuItem,
            (string) data_get($this->draftReview, 'branch.currency', 'EUR'),
        );
    }

    /**
     * @param  list<array{id: int, options: list<array{id: int}>}>  $modifierGroups
     * @return array<int, list<int>>
     */
    private function modifierOptionsFromSnapshots(mixed $selectedModifiers, array $modifierGroups): array
    {
        $selectedOptions = [];

        foreach ($modifierGroups as $modifierGroup) {
            $selectedOptions[$modifierGroup['id']] = [];
        }

        if (! is_array($selectedModifiers)) {
            return $selectedOptions;
        }

        foreach ($selectedModifiers as $modifier) {
            if (! is_array($modifier)) {
                continue;
            }

            $groupId = (int) ($modifier['group_id'] ?? 0);
            $optionId = (int) ($modifier['option_id'] ?? 0);
            $group = $this->findModifierGroup($modifierGroups, $groupId);

            if ($groupId > 0 && $optionId > 0 && $group !== null && $this->modifierGroupHasOption($group, $optionId)) {
                $selectedOptions[$groupId][] = $optionId;
            }
        }

        foreach ($selectedOptions as $groupId => $optionIds) {
            $selectedOptions[$groupId] = array_values(array_unique($optionIds));
        }

        return $selectedOptions;
    }

    /**
     * @param  list<array{id: int, max_select: int, options: list<array{id: int}>}>  $modifierGroups
     * @param  array<int, list<int>>  $selectedModifierOptions
     * @return array<int, list<int>>
     */
    private function toggledModifierOptions(array $modifierGroups, array $selectedModifierOptions, int $groupId, int $optionId): array
    {
        $group = $this->findModifierGroup($modifierGroups, $groupId);

        if ($group === null || ! $this->modifierGroupHasOption($group, $optionId)) {
            return $selectedModifierOptions;
        }

        $selected = $this->selectedOptionIdsForGroup($modifierGroups, $selectedModifierOptions, $groupId);

        if (in_array($optionId, $selected, true)) {
            $selectedModifierOptions[$groupId] = array_values(array_filter($selected, static fn (int $id): bool => $id !== $optionId));

            return $selectedModifierOptions;
        }

        $maxSelect = max(0, (int) $group['max_select']);

        if ($maxSelect === 0 || count($selected) >= $maxSelect) {
            return $selectedModifierOptions;
        }

        $selectedModifierOptions[$groupId] = $maxSelect === 1 ? [$optionId] : [...$selected, $optionId];

        return $selectedModifierOptions;
    }

    /**
     * @param  list<array{id: int, options: list<array{id: int}>}>  $modifierGroups
     * @return list<int>
     */
    private function selectedOptionIdsForGroup(array $modifierGroups, array $selectedModifierOptions, int $groupId): array
    {
        $group = $this->findModifierGroup($modifierGroups, $groupId);

        if ($group === null) {
            return [];
        }

        $availableOptionIds = collect($group['options'])
            ->pluck('id')
            ->map(static fn (mixed $optionId): int => (int) $optionId)
            ->all();

        return collect($selectedModifierOptions[$groupId] ?? [])
            ->map(static fn (mixed $optionId): int => (int) $optionId)
            ->filter(static fn (int $optionId): bool => in_array($optionId, $availableOptionIds, true))
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<array{id: int}> $modifierGroups @return array<string, mixed>|null */
    private function findModifierGroup(array $modifierGroups, int $groupId): ?array
    {
        foreach ($modifierGroups as $modifierGroup) {
            if ((int) $modifierGroup['id'] === $groupId) {
                return $modifierGroup;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $modifierGroup */
    private function modifierGroupHasOption(array $modifierGroup, int $optionId): bool
    {
        return collect($modifierGroup['options'] ?? [])
            ->contains(static fn (array $modifierOption): bool => (int) $modifierOption['id'] === $optionId);
    }

    private function refreshAddingItemTotal(): void
    {
        $this->addingItemTotal = $this->configuredItemTotal(
            $this->addingUnitPrice,
            '0.00',
            $this->addingQuantity,
            $this->addingModifierGroups,
            $this->addingModifierOptions,
        );
    }

    /**
     * @param  list<array{id: int, price_cents: int}>  $variants
     */
    private function selectedVariantPrice(array $variants, string $variantId, string $fallback): string
    {
        $variant = collect($variants)->firstWhere('id', (int) $variantId);

        return is_array($variant) ? MoneyFormatter::centsToDecimal((int) $variant['price_cents']) : $fallback;
    }

    private function nullableId(string $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function refreshEditingItemTotal(): void
    {
        $this->editingItemTotal = $this->configuredItemTotal(
            $this->editingUnitPrice,
            $this->editingModifierTotal,
            $this->editingQuantity,
            $this->editingModifierGroups,
            $this->editingModifierOptions,
        );
    }

    /**
     * @param  list<array{id: int, options: list<array{id: int, price_delta_cents: int}>}>  $modifierGroups
     * @param  array<int, list<int>>  $selectedModifierOptions
     */
    private function configuredItemTotal(string $unitPrice, string $fallbackModifierTotal, int $quantity, array $modifierGroups, array $selectedModifierOptions): string
    {
        $modifierTotalCents = $modifierGroups === [] ? MoneyFormatter::decimalToCents($fallbackModifierTotal) : 0;

        foreach ($modifierGroups as $modifierGroup) {
            $selectedOptionIds = $this->selectedOptionIdsForGroup($modifierGroups, $selectedModifierOptions, (int) $modifierGroup['id']);

            foreach ($modifierGroup['options'] as $modifierOption) {
                if (in_array((int) $modifierOption['id'], $selectedOptionIds, true)) {
                    $modifierTotalCents += (int) $modifierOption['price_delta_cents'];
                }
            }
        }

        $quantity = max(1, min(99, $quantity));

        return MoneyFormatter::centsToDecimal(max(0, MoneyFormatter::decimalToCents($unitPrice) + $modifierTotalCents) * $quantity);
    }

    private function resetAddingForm(): void
    {
        $this->addingMenuItemId = '';
        $this->addingQuantity = 1;
        $this->addingItemName = '';
        $this->addingUnitPrice = '0.00';
        $this->addingItemVariantId = '';
        $this->addingVariants = [];
        $this->addingItemTotal = '0.00';
        $this->addingComment = '';
        $this->addingModifierOptions = [];
        $this->addingModifierGroups = [];
    }

    /** @param array<string, mixed> $table @return array<string, mixed> */
    private function draftReviewPayload(array $table): array
    {
        return collect($table)
            ->only(['branch', 'guest_sections', 'draft', 'manual_order', 'current_draft_total', 'total'])
            ->all();
    }
}
