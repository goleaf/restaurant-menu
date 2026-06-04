<?php

namespace App\Livewire\Waiter;

use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Payments\ClosePaidTableSessionAction;
use App\Actions\Payments\RecordManualPaymentAction;
use App\Actions\Waiter\AddDraftOrderItemByWaiterAction;
use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\DeleteDraftOrderItemByWaiterAction;
use App\Actions\Waiter\MarkKitchenTicketItemServedAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
use App\Actions\Waiter\ReturnRejectedDraftOrderToDraftAction;
use App\Actions\Waiter\UpdateDraftOrderItemByWaiterAction;
use App\Enums\ManualPaymentMethod;
use App\Enums\MenuStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Table detail')]
class TableDetail extends Component
{
    public int $tableSessionId;

    /**
     * @var array<string, mixed>
     */
    public array $table = [];

    public string $refreshedAt = '';

    public string $rejectionReason = '';

    public string $reviewFeedbackMessage = '';

    public string $paymentFeedbackMessage = '';

    public string $paymentMethod = 'cash';

    public string $paymentNote = '';

    /**
     * @var list<array{value: string, label: string, price: string}>
     */
    public array $addableMenuItems = [];

    public ?int $addableMenuItemsBranchId = null;

    public string $addingGuestId = '';

    public string $addingMenuItemId = '';

    public int $addingQuantity = 1;

    public string $addingItemName = '';

    public string $addingUnitPrice = '0.00';

    public string $addingItemTotal = '0.00';

    public string $addingComment = '';

    /**
     * @var array<string, list<int>>
     */
    public array $addingModifierOptions = [];

    /**
     * @var list<array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta: string}>}>
     */
    public array $addingModifierGroups = [];

    public ?int $editingItemId = null;

    public string $editingItemName = '';

    public int $editingQuantity = 1;

    public string $editingUnitPrice = '0.00';

    public string $editingModifierTotal = '0.00';

    public string $editingItemTotal = '0.00';

    public string $editingComment = '';

    /**
     * @var array<string, list<int>>
     */
    public array $editingModifierOptions = [];

    /**
     * @var list<array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta: string}>}>
     */
    public array $editingModifierGroups = [];

    public function mount(TableSession $tableSession): void
    {
        $this->tableSessionId = $tableSession->id;
        $this->refreshTable();
    }

    public function refreshTable(): void
    {
        $tableSession = TableSession::query()
            ->select(['id', 'branch_id'])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();

        $payload = app(BuildWaiterTableDetailAction::class)->handle($this->currentUser(), $tableSession);

        if (! $payload['has_access']) {
            abort(403);
        }

        $this->table = $payload['table'] ?? [];
        $this->syncAddableMenuItems();
        $this->refreshedAt = now()->format('H:i:s');
    }

    public function updatedAddingMenuItemId(): void
    {
        $this->prepareAddingMenuItem();
    }

    public function updatedAddingQuantity(): void
    {
        $this->refreshAddingItemTotal();
    }

    public function updatedEditingQuantity(): void
    {
        $this->refreshEditingItemTotal();
    }

    public function toggleAddingModifierOption(int $modifierGroupId, int $modifierOptionId): void
    {
        $this->addingModifierOptions = $this->toggledModifierOptions(
            modifierGroups: $this->addingModifierGroups,
            selectedModifierOptions: $this->addingModifierOptions,
            modifierGroupId: $modifierGroupId,
            modifierOptionId: $modifierOptionId,
        );

        $this->refreshAddingItemTotal();
    }

    public function toggleEditingModifierOption(int $modifierGroupId, int $modifierOptionId): void
    {
        $this->editingModifierOptions = $this->toggledModifierOptions(
            modifierGroups: $this->editingModifierGroups,
            selectedModifierOptions: $this->editingModifierOptions,
            modifierGroupId: $modifierGroupId,
            modifierOptionId: $modifierOptionId,
        );

        $this->refreshEditingItemTotal();
    }

    public function addDraftItem(AddDraftOrderItemByWaiterAction $addDraftOrderItem): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $draftOrder = $this->currentDraftOrder();
        $guest = $this->selectedAddingGuest();
        $menuItem = $this->selectedAddingMenuItem();

        if (! $draftOrder instanceof DraftOrder || ! $guest instanceof TableSessionGuest || ! $menuItem instanceof MenuItem) {
            $this->addError('draft_edit', __('Выберите гостя и блюдо перед добавлением позиции.'));

            return;
        }

        try {
            $addDraftOrderItem->handle(
                draftOrder: $draftOrder,
                guest: $guest,
                menuItem: $menuItem,
                editedBy: $this->currentUser(),
                quantity: (int) $this->addingQuantity,
                selectedModifierOptions: $this->addingModifierOptions,
                comment: $this->addingComment,
                itemName: $this->addingItemName,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('Позиция добавлена. Гости увидят обновлённый черновик.');
        $this->resetAddingForm();
        $this->refreshTable();
    }

    public function editDraftItem(int $itemId): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        if (! data_get($this->table, 'draft.can_edit')) {
            $this->addError('draft_edit', __('У вас нет права редактировать этот черновик.'));

            return;
        }

        $draftOrderItem = $this->draftOrderItemForCurrentTable($itemId);

        if (! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_edit', __('Позиция не найдена в этом черновике.'));

            return;
        }

        $this->editingItemId = $draftOrderItem->id;
        $this->editingItemName = $draftOrderItem->item_name;
        $this->editingQuantity = max(1, min(99, (int) $draftOrderItem->quantity));
        $this->editingUnitPrice = $draftOrderItem->unit_price;
        $this->editingModifierTotal = $draftOrderItem->modifier_total;
        $this->editingComment = (string) $draftOrderItem->comment;
        $this->editingModifierGroups = $this->modifierGroupPayloadFor($draftOrderItem->menuItem);
        $this->editingModifierOptions = $this->modifierOptionsFromSnapshots($draftOrderItem->selected_modifiers, $this->editingModifierGroups);

        $this->refreshEditingItemTotal();
    }

    public function closeEditDraftItem(): void
    {
        $this->resetValidation();
        $this->editingItemId = null;
        $this->editingItemName = '';
        $this->editingQuantity = 1;
        $this->editingUnitPrice = '0.00';
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

        if ($this->editingItemId === null) {
            return;
        }

        $draftOrderItem = DraftOrderItem::query()
            ->select(['id'])
            ->whereKey($this->editingItemId)
            ->first();

        if (! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_edit', __('Позиция не найдена.'));

            return;
        }

        try {
            $updateDraftOrderItem->handle(
                draftOrderItem: $draftOrderItem,
                editedBy: $this->currentUser(),
                quantity: (int) $this->editingQuantity,
                selectedModifierOptions: $this->editingModifierOptions,
                comment: $this->editingComment,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('Позиция обновлена. Гости увидят актуальную сумму.');
        $this->closeEditDraftItem();
        $this->refreshTable();
    }

    public function deleteDraftItem(int $itemId): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $draftOrderItem = DraftOrderItem::query()
            ->select(['id'])
            ->whereKey($itemId)
            ->first();

        if (! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_edit', __('Позиция не найдена.'));

            return;
        }

        try {
            app(DeleteDraftOrderItemByWaiterAction::class)->handle($draftOrderItem, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        if ($this->editingItemId === $itemId) {
            $this->closeEditDraftItem();
        }

        $this->reviewFeedbackMessage = __('Позиция удалена. Гости увидят обновлённый черновик.');
        $this->refreshTable();
    }

    public function confirmDraft(ConfirmDraftOrderByWaiterAction $confirmDraftOrder): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('У этого стола нет черновика для подтверждения.'));

            return;
        }

        try {
            $order = $confirmDraftOrder->handle($draftOrder, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->rejectionReason = '';
        $this->reviewFeedbackMessage = __('Заказ подтверждён официантом. Кухня и бар пока не получают его автоматически.');
        $this->refreshTable();
        $this->table['draft']['order_id'] = $order->id;
    }

    public function sendOrderToKitchenBar(SendOrderToKitchenBarAction $sendOrderToKitchenBar): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $order = $this->currentOrder();

        if (! $order instanceof Order) {
            $this->addError('order_dispatch', __('Сначала подтвердите заказ официантом.'));

            return;
        }

        try {
            $sendOrderToKitchenBar->handle($order, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('Заказ отправлен на кухню/бар. Гости увидят, что заказ принят.');
        $this->refreshTable();
    }

    public function markTicketItemServed(int $ticketItemId, MarkKitchenTicketItemServedAction $markKitchenTicketItemServed): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $ticketItem = KitchenTicketItem::query()
            ->select(['id'])
            ->whereKey($ticketItemId)
            ->first();

        if (! $ticketItem instanceof KitchenTicketItem) {
            $this->addError('order_service', __('Позиция не найдена.'));

            return;
        }

        try {
            $markKitchenTicketItemServed->handle($ticketItem, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('Позиция отмечена как поданная. Гости увидят обновлённый статус.');
        $this->refreshTable();
    }

    public function rejectDraft(RejectDraftOrderByWaiterAction $rejectDraftOrder): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('У этого стола нет черновика для отклонения.'));

            return;
        }

        try {
            $rejectDraftOrder->handle($draftOrder, $this->currentUser(), $this->rejectionReason);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('Черновик отклонён. Гости увидят причину.');
        $this->refreshTable();
    }

    public function returnRejectedDraftToDraft(ReturnRejectedDraftOrderToDraftAction $returnDraft): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('У этого стола нет черновика для возврата.'));

            return;
        }

        try {
            $returnDraft->handle($draftOrder, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->rejectionReason = '';
        $this->reviewFeedbackMessage = __('Черновик возвращён гостям для правок.');
        $this->refreshTable();
    }

    public function recordTablePayment(RecordManualPaymentAction $recordManualPayment): void
    {
        $this->resetValidation();
        $this->paymentFeedbackMessage = '';

        try {
            $recordManualPayment->recordTable(
                tableSession: $this->currentTableSession(),
                recordedBy: $this->currentUser(),
                paymentMethod: $this->currentPaymentMethod(),
                note: $this->paymentNote,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->paymentNote = '';
        $this->paymentFeedbackMessage = __('Оплата всего стола отмечена.');
        $this->refreshTable();
    }

    public function recordGuestPayment(int $guestId, RecordManualPaymentAction $recordManualPayment): void
    {
        $this->resetValidation();
        $this->paymentFeedbackMessage = '';

        $guest = $this->paymentGuestForCurrentTable($guestId);

        if (! $guest instanceof TableSessionGuest) {
            $this->addError('manual_payment', __('Гость не найден в этой сессии.'));

            return;
        }

        try {
            $recordManualPayment->recordGuest(
                tableSession: $this->currentTableSession(),
                guest: $guest,
                recordedBy: $this->currentUser(),
                paymentMethod: $this->currentPaymentMethod(),
                note: $this->paymentNote,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->paymentNote = '';
        $this->paymentFeedbackMessage = __('Оплата гостя отмечена.');
        $this->refreshTable();
    }

    public function closePaidSession(ClosePaidTableSessionAction $closePaidTableSession): void
    {
        $this->resetValidation();
        $this->paymentFeedbackMessage = '';

        try {
            $closePaidTableSession->handle($this->currentTableSession(), $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->paymentFeedbackMessage = __('Стол закрыт. Место свободно для следующих гостей.');
        $this->refreshTable();
    }

    public function render(): View
    {
        return view('livewire.waiter.table-detail');
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function currentDraftOrder(): ?DraftOrder
    {
        $tableSession = TableSession::query()
            ->select(['id'])
            ->with(['draftOrder' => fn ($query) => $query->select(['draft_orders.id', 'draft_orders.table_session_id', 'draft_orders.status'])])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();

        return $tableSession->draftOrder;
    }

    private function currentOrder(): ?Order
    {
        $tableSession = TableSession::query()
            ->select(['id'])
            ->with([
                'draftOrder' => fn ($query) => $query
                    ->select(['draft_orders.id', 'draft_orders.table_session_id'])
                    ->with(['order' => fn ($orderQuery) => $orderQuery->select(['id', 'draft_order_id', 'status'])]),
            ])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();

        return $tableSession->draftOrder?->order;
    }

    private function currentTableSession(): TableSession
    {
        return TableSession::query()
            ->select(['id'])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();
    }

    private function currentPaymentMethod(): ManualPaymentMethod
    {
        return ManualPaymentMethod::tryFrom($this->paymentMethod) ?? ManualPaymentMethod::Cash;
    }

    private function paymentGuestForCurrentTable(int $guestId): ?TableSessionGuest
    {
        if ($guestId < 1) {
            return null;
        }

        return TableSessionGuest::query()
            ->select(['id', 'table_session_id', 'guest_name'])
            ->where('table_session_id', $this->tableSessionId)
            ->whereKey($guestId)
            ->first();
    }

    private function selectedAddingGuest(): ?TableSessionGuest
    {
        $guestId = (int) $this->addingGuestId;

        if ($guestId < 1) {
            return null;
        }

        return TableSessionGuest::query()
            ->select(['id'])
            ->whereKey($guestId)
            ->first();
    }

    private function selectedAddingMenuItem(): ?MenuItem
    {
        $menuItemId = (int) $this->addingMenuItemId;

        if ($menuItemId < 1) {
            return null;
        }

        return MenuItem::query()
            ->select(['id'])
            ->whereKey($menuItemId)
            ->first();
    }

    private function draftOrderItemForCurrentTable(int $itemId): ?DraftOrderItem
    {
        $draftOrderItem = DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
                'table_session_guest_id',
                'menu_item_id',
                'item_name',
                'quantity',
                'unit_price',
                'modifier_total',
                'total_price',
                'selected_modifiers',
                'comment',
            ])
            ->with([
                'draftOrder' => fn ($query) => $query->select([
                    'id',
                    'table_session_id',
                    'status',
                ]),
                'menuItem' => fn ($query) => $query->select(['id']),
            ])
            ->whereKey($itemId)
            ->first();

        if (! $draftOrderItem instanceof DraftOrderItem
            || $draftOrderItem->draftOrder?->table_session_id !== $this->tableSessionId) {
            return null;
        }

        return $draftOrderItem;
    }

    /**
     * @return list<array{value: string, label: string, price: string}>
     */
    private function menuItemOptionsForCurrentBranch(): array
    {
        $branchId = (int) data_get($this->table, 'branch.id');

        if ($branchId < 1) {
            return [];
        }

        return MenuItem::query()
            ->select(['id', 'menu_id', 'category_id', 'name', 'price', 'is_available', 'sort_order'])
            ->with([
                'menu' => fn ($query) => $query->select(['id', 'branch_id', 'status', 'name']),
                'category' => fn ($query) => $query->select(['id', 'menu_id', 'name', 'is_active']),
            ])
            ->whereHas('menu', function ($query) use ($branchId): void {
                $query
                    ->where('branch_id', $branchId)
                    ->where('status', MenuStatus::Active->value);
            })
            ->whereHas('category', function ($query): void {
                $query->where('is_active', true);
            })
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (MenuItem $menuItem): array => [
                'value' => (string) $menuItem->id,
                'label' => trim(($menuItem->category?->name ? $menuItem->category->name.' · ' : '').$menuItem->name),
                'price' => $menuItem->price,
            ])
            ->values()
            ->all();
    }

    private function syncAddableMenuItems(): void
    {
        $canEditDraft = (bool) data_get($this->table, 'draft.can_edit');
        $branchId = (int) data_get($this->table, 'branch.id');

        if (! $canEditDraft || $branchId < 1) {
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
        $this->addingModifierOptions = [];
        $this->addingModifierGroups = [];

        $menuItem = $this->configuredMenuItem((int) $this->addingMenuItemId);

        if (! $menuItem instanceof MenuItem) {
            return;
        }

        $this->addingItemName = $menuItem->name;
        $this->addingUnitPrice = $menuItem->price;
        $this->addingModifierGroups = $this->modifierGroupPayloadFor($menuItem);
        $this->addingModifierOptions = collect($this->addingModifierGroups)
            ->mapWithKeys(fn (array $modifierGroup): array => [(string) $modifierGroup['id'] => []])
            ->all();

        $this->refreshAddingItemTotal();
    }

    private function configuredMenuItem(int $menuItemId): ?MenuItem
    {
        if ($menuItemId < 1) {
            return null;
        }

        $allowedMenuItemIds = collect($this->addableMenuItems)
            ->pluck('value')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        if (! in_array($menuItemId, $allowedMenuItemIds, true)) {
            return null;
        }

        return MenuItem::query()
            ->select(['id', 'menu_id', 'category_id', 'name', 'price', 'is_available'])
            ->whereKey($menuItemId)
            ->first();
    }

    /**
     * @return list<array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta: string}>}>
     */
    private function modifierGroupPayloadFor(?MenuItem $menuItem): array
    {
        if (! $menuItem instanceof MenuItem) {
            return [];
        }

        return app(BuildDraftOrderItemModifierSnapshots::class)
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
                        'price_delta' => $modifierOption->price_delta,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: int, options: list<array{id: int}>}>  $modifierGroups
     * @return array<string, list<int>>
     */
    private function modifierOptionsFromSnapshots(mixed $selectedModifiers, array $modifierGroups): array
    {
        $selectedOptions = collect($modifierGroups)
            ->mapWithKeys(fn (array $modifierGroup): array => [(string) $modifierGroup['id'] => []])
            ->all();

        if (! is_array($selectedModifiers)) {
            return $selectedOptions;
        }

        collect($selectedModifiers)->each(function (mixed $modifier) use (&$selectedOptions, $modifierGroups): void {
            if (! is_array($modifier)) {
                return;
            }

            $groupId = (int) ($modifier['group_id'] ?? 0);
            $optionId = (int) ($modifier['option_id'] ?? 0);

            if ($groupId < 1 || $optionId < 1) {
                return;
            }

            $group = collect($modifierGroups)->first(fn (array $modifierGroup): bool => (int) $modifierGroup['id'] === $groupId);

            if ($group === null) {
                return;
            }

            $availableOptionIds = collect($group['options'])
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if (in_array($optionId, $availableOptionIds, true)) {
                $selectedOptions[(string) $groupId][] = $optionId;
            }
        });

        return collect($selectedOptions)
            ->map(fn (array $optionIds): array => collect($optionIds)->unique()->values()->all())
            ->all();
    }

    /**
     * @param  list<array{id: int, max_select: int, options: list<array{id: int}>}>  $modifierGroups
     * @param  array<string, list<int>>  $selectedModifierOptions
     * @return array<string, list<int>>
     */
    private function toggledModifierOptions(array $modifierGroups, array $selectedModifierOptions, int $modifierGroupId, int $modifierOptionId): array
    {
        $group = $this->findModifierGroup($modifierGroups, $modifierGroupId);

        if ($group === null || ! $this->modifierGroupHasOption($group, $modifierOptionId)) {
            return $selectedModifierOptions;
        }

        $selected = $this->selectedOptionIdsForGroup($modifierGroups, $selectedModifierOptions, $modifierGroupId);

        if (in_array($modifierOptionId, $selected, true)) {
            $selectedModifierOptions[(string) $modifierGroupId] = array_values(array_filter(
                $selected,
                fn (int $selectedOptionId): bool => $selectedOptionId !== $modifierOptionId,
            ));

            return $selectedModifierOptions;
        }

        $maxSelect = max(0, (int) $group['max_select']);

        if ($maxSelect === 0) {
            return $selectedModifierOptions;
        }

        if ($maxSelect === 1) {
            $selectedModifierOptions[(string) $modifierGroupId] = [$modifierOptionId];

            return $selectedModifierOptions;
        }

        if (count($selected) >= $maxSelect) {
            return $selectedModifierOptions;
        }

        $selected[] = $modifierOptionId;
        $selectedModifierOptions[(string) $modifierGroupId] = array_values($selected);

        return $selectedModifierOptions;
    }

    /**
     * @param  list<array{id: int, options: list<array{id: int}>}>  $modifierGroups
     * @return list<int>
     */
    private function selectedOptionIdsForGroup(array $modifierGroups, array $selectedModifierOptions, int $modifierGroupId): array
    {
        $group = $this->findModifierGroup($modifierGroups, $modifierGroupId);

        if ($group === null) {
            return [];
        }

        $availableOptionIds = collect($group['options'])
            ->pluck('id')
            ->map(fn (mixed $optionId): int => (int) $optionId)
            ->all();

        return collect($selectedModifierOptions[(string) $modifierGroupId] ?? [])
            ->map(fn (mixed $optionId): int => (int) $optionId)
            ->filter(fn (int $optionId): bool => in_array($optionId, $availableOptionIds, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: int}>  $modifierGroups
     * @return array<string, mixed>|null
     */
    private function findModifierGroup(array $modifierGroups, int $modifierGroupId): ?array
    {
        foreach ($modifierGroups as $modifierGroup) {
            if ((int) $modifierGroup['id'] === $modifierGroupId) {
                return $modifierGroup;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $modifierGroup
     */
    private function modifierGroupHasOption(array $modifierGroup, int $modifierOptionId): bool
    {
        return collect($modifierGroup['options'] ?? [])
            ->contains(fn (array $modifierOption): bool => (int) $modifierOption['id'] === $modifierOptionId);
    }

    private function refreshAddingItemTotal(): void
    {
        $this->addingItemTotal = $this->configuredItemTotal(
            unitPrice: $this->addingUnitPrice,
            fallbackModifierTotal: '0.00',
            quantity: $this->addingQuantity,
            modifierGroups: $this->addingModifierGroups,
            selectedModifierOptions: $this->addingModifierOptions,
        );
    }

    private function refreshEditingItemTotal(): void
    {
        $this->editingItemTotal = $this->configuredItemTotal(
            unitPrice: $this->editingUnitPrice,
            fallbackModifierTotal: $this->editingModifierTotal,
            quantity: $this->editingQuantity,
            modifierGroups: $this->editingModifierGroups,
            selectedModifierOptions: $this->editingModifierOptions,
        );
    }

    /**
     * @param  list<array{id: int, options: list<array{id: int, price_delta: string}>}>  $modifierGroups
     * @param  array<string, list<int>>  $selectedModifierOptions
     */
    private function configuredItemTotal(string $unitPrice, string $fallbackModifierTotal, int $quantity, array $modifierGroups, array $selectedModifierOptions): string
    {
        $modifierTotalCents = $modifierGroups === []
            ? self::decimalToCents($fallbackModifierTotal)
            : 0;

        foreach ($modifierGroups as $modifierGroup) {
            $selectedOptionIds = $this->selectedOptionIdsForGroup($modifierGroups, $selectedModifierOptions, (int) $modifierGroup['id']);

            foreach ($modifierGroup['options'] as $modifierOption) {
                if (in_array((int) $modifierOption['id'], $selectedOptionIds, true)) {
                    $modifierTotalCents += self::decimalToCents((string) $modifierOption['price_delta']);
                }
            }
        }

        $quantity = max(1, min(99, (int) $quantity));
        $unitPriceCents = self::decimalToCents($unitPrice);

        return self::centsToDecimal(max(0, $unitPriceCents + $modifierTotalCents) * $quantity);
    }

    private function resetAddingForm(): void
    {
        $this->addingMenuItemId = '';
        $this->addingQuantity = 1;
        $this->addingItemName = '';
        $this->addingUnitPrice = '0.00';
        $this->addingItemTotal = '0.00';
        $this->addingComment = '';
        $this->addingModifierOptions = [];
        $this->addingModifierGroups = [];
    }

    private function showValidationException(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($field, $messages[0] ?? __('Ошибка проверки.'));
        }
    }

    private static function decimalToCents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    private static function centsToDecimal(int $amount): string
    {
        $negative = $amount < 0;
        $absoluteAmount = abs($amount);
        $formatted = intdiv($absoluteAmount, 100).'.'.str_pad((string) ($absoluteAmount % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
