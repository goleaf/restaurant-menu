<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\DraftOrders\DeleteGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\DraftOrders\UpdateGuestDraftOrderItemAction;
use App\Actions\TableSessions\RequestBillForTableSessionAction;
use App\Actions\TableSessions\ToggleTableSessionGuestReadyAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder as DraftOrderModel;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Services\PublicQr\PublicQrQueryService;
use App\Support\MoneyFormatter;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Isolate]
class DraftOrder extends Component
{
    private ToggleTableSessionGuestReadyAction $toggleGuestReady;

    private SendDraftOrderToWaiterAction $sendDraftOrderToWaiter;

    private UpdateGuestDraftOrderItemAction $updateGuestDraftOrderItem;

    private DeleteGuestDraftOrderItemAction $deleteGuestDraftOrderItem;

    private BuildDraftOrderItemModifierSnapshots $buildModifierSnapshots;

    private PublicQrQueryService $publicQrQueries;

    #[Locked]
    public int $tableSessionId = 0;

    #[Locked]
    public int $currentGuestId = 0;

    #[Locked]
    public string $publicToken = '';

    public string $currency = 'EUR';

    public string $language = 'ru';

    public int $pollingIntervalSeconds = 1;

    public bool $branchCanAcceptOrders = true;

    public string $branchOpeningStatusMessage = '';

    public bool $showControls = true;

    public bool $showTotals = true;

    public bool $showStatuses = true;

    /**
     * @var list<array{id: int, guest_id: int, guest_name: string, item_name: string, quantity: int, unit_price: string, modifier_total: string, unit_total_price: string, total_price: string, modifiers: list<string>, comment: string|null, is_current_guest: bool, can_edit: bool}>
     */
    public array $items = [];

    /**
     * @var list<array{guest_id: int, guest_name: string, total: string, draft_total: string, confirmed_total: string, has_draft_total: bool, has_confirmed_total: bool, is_current_guest: bool, is_ready: bool, items: list<array{id: int, guest_id: int, guest_name: string, item_name: string, quantity: int, unit_price: string, modifier_total: string, unit_total_price: string, total_price: string, modifiers: list<string>, comment: string|null, is_current_guest: bool, can_edit: bool}>}>
     */
    public array $guestSections = [];

    /**
     * @var list<array{guest_id: int, guest_name: string, total: string, is_current_guest: bool}>
     */
    public array $guestTotals = [];

    public string $totalAmount = '0.00';

    public string $confirmedOrdersTotalAmount = '0.00';

    public string $tableTotalAmount = '0.00';

    public bool $hasConfirmedOrders = false;

    public int $itemCount = 0;

    public bool $canEditDraft = true;

    public bool $canToggleReadyStatus = false;

    public bool $canSendDraftToWaiter = false;

    public bool $canRequestBill = false;

    public bool $billRequested = false;

    public bool $sendNeedsReadyConfirmation = false;

    public ?string $tableSessionStatusValue = null;

    public string $tableSessionStatusLabel = '';

    public ?string $draftStatusValue = null;

    public string $draftStatusLabel = '';

    public ?string $orderStatusValue = null;

    public string $orderStatusLabel = '';

    public string $serviceStatusValue = '';

    public string $serviceStatusLabel = '';

    public string $serviceStatusTone = 'zinc';

    public ?string $rejectionReason = null;

    public bool $currentGuestReady = false;

    public bool $allGuestsReady = false;

    public int $activeGuestCount = 0;

    public int $readyGuestCount = 0;

    public string $feedbackMessage = '';

    public ?int $editingItemId = null;

    public string $editingItemName = '';

    public int $editingQuantity = 1;

    public string $editingUnitPrice = '0.00';

    public string $editingItemVariantId = '';

    /** @var list<array{id: int, name: string, price_cents: int, formatted_price: string}> */
    public array $editingVariants = [];

    public string $editingModifierTotal = '0.00';

    public string $editingItemTotal = '0.00';

    public string $editingComment = '';

    /**
     * @var array<int, list<int>>
     */
    public array $editingModifierOptions = [];

    /**
     * @var list<array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta_cents: int, formatted_price_delta: string}>}>
     */
    public array $editingModifierGroups = [];

    public function boot(
        ToggleTableSessionGuestReadyAction $toggleGuestReady,
        SendDraftOrderToWaiterAction $sendDraftOrderToWaiter,
        UpdateGuestDraftOrderItemAction $updateGuestDraftOrderItem,
        DeleteGuestDraftOrderItemAction $deleteGuestDraftOrderItem,
        BuildDraftOrderItemModifierSnapshots $buildModifierSnapshots,
        PublicQrQueryService $publicQrQueries,
    ): void {
        $this->toggleGuestReady = $toggleGuestReady;
        $this->sendDraftOrderToWaiter = $sendDraftOrderToWaiter;
        $this->updateGuestDraftOrderItem = $updateGuestDraftOrderItem;
        $this->deleteGuestDraftOrderItem = $deleteGuestDraftOrderItem;
        $this->buildModifierSnapshots = $buildModifierSnapshots;
        $this->publicQrQueries = $publicQrQueries;
    }

    public function mount(
        int $tableSessionId,
        int $currentGuestId,
        string $currency = 'EUR',
        string $publicToken = '',
        int $pollingIntervalSeconds = 1,
        bool $branchCanAcceptOrders = true,
        string $branchOpeningStatusMessage = '',
        bool $showControls = true,
        bool $showTotals = true,
        bool $showStatuses = true,
        string $language = 'ru',
    ): void {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->currency = $currency;
        $this->publicToken = $publicToken;
        $this->language = SupportedLocale::normalize($language, 'ru');
        $this->pollingIntervalSeconds = GetBranchPollingIntervalAction::normalize($pollingIntervalSeconds);
        $this->branchCanAcceptOrders = $branchCanAcceptOrders;
        $this->branchOpeningStatusMessage = $branchOpeningStatusMessage;
        $this->showControls = $showControls;
        $this->showTotals = $showTotals;
        $this->showStatuses = $showStatuses;
        $this->applyLocale();

        $this->refreshDraft();
    }

    public function refreshDraft(): void
    {
        $this->applyLocale();

        $guests = $this->activeGuests();
        $draftOrder = $this->draftOrder($this->showStatuses);
        $draftItems = $draftOrder instanceof DraftOrderModel ? $draftOrder->items : collect();
        $loadsTotals = $this->showTotals || $this->showControls;
        $tableSession = $this->showControls || $this->showStatuses ? $this->tableSessionForBillState() : null;
        $guestSections = [];
        $totalCents = 0;
        $confirmedOrdersTotalCents = $loadsTotals ? $this->confirmedOrdersTotalCents() : 0;
        $confirmedGuestTotals = $loadsTotals ? $this->confirmedOrderItemGuestTotals() : [];

        $this->tableSessionStatusValue = $tableSession?->status?->value;
        $this->tableSessionStatusLabel = $tableSession?->status?->label() ?? '';
        $this->billRequested = $this->showControls && $tableSession?->status === TableSessionStatus::PaymentRequested;
        $this->draftStatusValue = $draftOrder?->status?->value;
        $this->draftStatusLabel = $draftOrder?->status?->label() ?? '';
        $orderStatus = $this->showStatuses && $draftOrder?->order?->status instanceof OrderStatus
            ? $draftOrder->order->status
            : null;
        $ticketItems = $this->showStatuses ? $this->orderTicketItems($draftOrder) : collect();
        $serviceStatus = $this->showStatuses
            ? $this->guestServiceStatus($draftOrder, $orderStatus, $ticketItems)
            : ['value' => '', 'label' => '', 'tone' => 'zinc'];

        $this->orderStatusValue = $orderStatus?->value;
        $this->orderStatusLabel = $orderStatus?->label() ?? '';
        $this->serviceStatusValue = $serviceStatus['value'];
        $this->serviceStatusLabel = $serviceStatus['label'];
        $this->serviceStatusTone = $serviceStatus['tone'];
        $this->rejectionReason = $this->showStatuses ? $draftOrder?->rejection_reason : null;
        $this->canEditDraft = $draftOrder === null || $draftOrder->status === DraftOrderStatus::Draft;
        $this->activeGuestCount = $guests->count();
        $this->readyGuestCount = $guests->filter(fn (TableSessionGuest $guest): bool => $guest->ready_at !== null)->count();
        $this->allGuestsReady = $this->activeGuestCount > 0 && $this->readyGuestCount === $this->activeGuestCount;
        $this->currentGuestReady = false;
        $this->canToggleReadyStatus = false;
        $this->canSendDraftToWaiter = false;
        $this->canRequestBill = false;

        $guests->each(function (TableSessionGuest $guest) use (&$guestSections, $tableSession): void {
            $isCurrentGuest = $guest->id === $this->currentGuestId;
            $isReady = $guest->ready_at !== null;

            if ($isCurrentGuest) {
                $this->currentGuestReady = $isReady;
                $this->canToggleReadyStatus = $this->showControls && $this->publicToken !== '' && $this->canEditDraft;
                $this->canSendDraftToWaiter = $this->showControls && $this->publicToken !== '' && $this->canEditDraft && $this->branchCanAcceptOrders;
                $this->canRequestBill = $this->showControls
                    && $this->publicToken !== ''
                    && $tableSession instanceof TableSession
                    && ! $this->billRequested
                    && ! in_array($tableSession->status, [
                        TableSessionStatus::Paid,
                        TableSessionStatus::Closed,
                        TableSessionStatus::Cancelled,
                    ], true);
            }

            $guestSections[$guest->id] = [
                'guest_id' => $guest->id,
                'guest_name' => $guest->guest_name,
                'draft_total_cents' => 0,
                'confirmed_total_cents' => 0,
                'total_cents' => 0,
                'is_current_guest' => $isCurrentGuest,
                'is_ready' => $isReady,
                'items' => [],
            ];
        });

        foreach ($confirmedGuestTotals as $index => $confirmedGuestTotal) {
            $guestId = (int) $confirmedGuestTotal['guest_id'];
            $guestKey = $guestId > 0 ? $guestId : 'confirmed-'.$index;
            $confirmedTotalCents = (int) $confirmedGuestTotal['total_cents'];

            if (! isset($guestSections[$guestKey])) {
                $guestSections[$guestKey] = [
                    'guest_id' => $guestId > 0 ? $guestId : -($index + 1),
                    'guest_name' => $confirmedGuestTotal['guest_name'],
                    'draft_total_cents' => 0,
                    'confirmed_total_cents' => 0,
                    'total_cents' => 0,
                    'is_current_guest' => $guestId === $this->currentGuestId,
                    'is_ready' => false,
                    'items' => [],
                ];
            }

            $guestSections[$guestKey]['confirmed_total_cents'] += $confirmedTotalCents;
            $guestSections[$guestKey]['total_cents'] += $confirmedTotalCents;
        }

        $items = $draftItems
            ->map(function (DraftOrderItem $item) use (&$guestSections, &$totalCents): array {
                $itemTotalCents = $item->total_price_cents;
                $unitTotalCents = max(0, $item->unit_price_cents + $item->modifier_total_cents);
                $totalCents += $itemTotalCents;
                $guestId = (int) $item->table_session_guest_id;
                $guestName = $item->guest->guest_name;

                if (! isset($guestSections[$guestId])) {
                    $guestSections[$guestId] = [
                        'guest_id' => $guestId,
                        'guest_name' => $guestName,
                        'draft_total_cents' => 0,
                        'confirmed_total_cents' => 0,
                        'total_cents' => 0,
                        'is_current_guest' => $guestId === $this->currentGuestId,
                        'is_ready' => false,
                        'items' => [],
                    ];
                }

                $guestSections[$guestId]['draft_total_cents'] += $itemTotalCents;
                $guestSections[$guestId]['total_cents'] += $itemTotalCents;

                $isCurrentGuest = $item->table_session_guest_id === $this->currentGuestId;

                $itemPayload = [
                    'id' => $item->id,
                    'guest_id' => $guestId,
                    'guest_name' => $guestName,
                    'item_name' => $item->item_name,
                    'variant_name' => $item->variant_name,
                    'quantity' => $item->quantity,
                    'unit_price' => MoneyFormatter::centsToDecimal($item->unit_price_cents),
                    'modifier_total' => MoneyFormatter::centsToDecimal($item->modifier_total_cents),
                    'unit_total_price' => MoneyFormatter::centsToDecimal($unitTotalCents),
                    'total_price' => MoneyFormatter::centsToDecimal($item->total_price_cents),
                    'modifiers' => $this->modifierSummary($item->selected_modifiers),
                    'comment' => $item->comment,
                    'is_current_guest' => $isCurrentGuest,
                    'can_edit' => $isCurrentGuest && $this->canEditDraft && $this->publicToken !== '',
                ];

                $guestSections[$guestId]['items'][] = $itemPayload;

                return $itemPayload;
            });

        $this->items = $items->values()->all();
        $this->guestSections = collect($guestSections)
            ->sortBy(fn (array $guestSection): string => mb_strtolower($guestSection['guest_name']))
            ->map(fn (array $guestSection): array => [
                'guest_id' => $guestSection['guest_id'],
                'guest_name' => $guestSection['guest_name'],
                'total' => MoneyFormatter::centsToDecimal($guestSection['total_cents']),
                'draft_total' => MoneyFormatter::centsToDecimal($guestSection['draft_total_cents']),
                'confirmed_total' => MoneyFormatter::centsToDecimal($guestSection['confirmed_total_cents']),
                'has_draft_total' => $guestSection['draft_total_cents'] > 0,
                'has_confirmed_total' => $guestSection['confirmed_total_cents'] > 0,
                'is_current_guest' => $guestSection['is_current_guest'],
                'is_ready' => $guestSection['is_ready'],
                'items' => $guestSection['items'],
            ])
            ->values()
            ->all();
        $this->guestTotals = collect($this->guestSections)
            ->map(fn (array $guestSection): array => [
                'guest_id' => $guestSection['guest_id'],
                'guest_name' => $guestSection['guest_name'],
                'total' => $guestSection['total'],
                'is_current_guest' => $guestSection['is_current_guest'],
            ])
            ->all();

        $this->totalAmount = MoneyFormatter::centsToDecimal($totalCents);
        $this->confirmedOrdersTotalAmount = MoneyFormatter::centsToDecimal($confirmedOrdersTotalCents);
        $this->tableTotalAmount = MoneyFormatter::centsToDecimal($confirmedOrdersTotalCents + $this->openDraftTotalCents($draftOrder, $totalCents));
        $this->hasConfirmedOrders = $confirmedOrdersTotalCents > 0;
        $this->itemCount = count($this->items);
        $this->canSendDraftToWaiter = $this->canSendDraftToWaiter && $this->itemCount > 0;

        if (! $this->canSendDraftToWaiter || $this->allGuestsReady) {
            $this->sendNeedsReadyConfirmation = false;
        }
    }

    public function toggleReadyStatus(): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        $guest = $this->currentActiveGuest();

        if (! $guest instanceof TableSessionGuest) {
            $this->addError('ready_status', __('guest.table.ready_requires_active_guest'));

            return;
        }

        try {
            $guest = $this->toggleGuestReady->handle($guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->feedbackMessage = $guest->ready_at === null
            ? __('guest.table.not_ready_feedback')
            : __('guest.table.ready_feedback');

        $this->refreshDraft();
    }

    public function requestBill(RequestBillForTableSessionAction $requestBill): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        $guest = $this->currentActiveGuest();

        if (! $guest instanceof TableSessionGuest) {
            $this->addError('bill_request', __('guest.table.bill_requires_active_guest'));

            return;
        }

        $tableSession = $this->publicQrQueries->statusTableSession($this->tableSessionId);

        if (! $tableSession instanceof TableSession) {
            $this->addError('bill_request', __('guest.table.session_not_found'));

            return;
        }

        try {
            $requestBill->handle($tableSession, $guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->closeEditItem();
        $this->sendNeedsReadyConfirmation = false;
        $this->feedbackMessage = __('guest.table.bill_requested');
        $this->refreshDraft();
    }

    public function sendDraftToWaiter(bool $confirmedNotReady = false): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        if (! $this->allGuestsReady && ! $confirmedNotReady) {
            $this->sendNeedsReadyConfirmation = true;

            return;
        }

        $guest = $this->currentActiveGuest();
        $draftOrder = $this->draftOrderForSending();

        if (! $guest instanceof TableSessionGuest || ! $draftOrder instanceof DraftOrderModel) {
            $this->addError('send_draft', __('guest.table.send_requires_active_guest'));

            return;
        }

        try {
            $this->sendDraftOrderToWaiter->handle($draftOrder, $guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->closeEditItem();
        $this->sendNeedsReadyConfirmation = false;
        $this->feedbackMessage = __('guest.table.sent_to_waiter');
        $this->refreshDraft();
    }

    public function cancelSendDraftConfirmation(): void
    {
        $this->sendNeedsReadyConfirmation = false;
    }

    public function editItem(int $itemId): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        $guest = $this->currentActiveGuest();

        if (! $guest instanceof TableSessionGuest) {
            $this->addError('draft_item', __('guest.cart.edit_requires_active_guest'));

            return;
        }

        $draftOrderItem = $this->editableDraftOrderItem($itemId);

        if (! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_item', __('guest.cart.edit_own_items_only'));

            return;
        }

        if ($draftOrderItem->draftOrder->status !== DraftOrderStatus::Draft) {
            $this->addError('draft_order', __('guest.cart.draft_locked'));

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

    public function closeEditItem(): void
    {
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

    public function updatedEditingQuantity(): void
    {
        $this->refreshEditingItemTotal();
    }

    public function updatedEditingItemVariantId(): void
    {
        $variant = collect($this->editingVariants)->firstWhere('id', (int) $this->editingItemVariantId);

        if (is_array($variant)) {
            $this->editingUnitPrice = MoneyFormatter::centsToDecimal((int) $variant['price_cents']);
        }

        $this->refreshEditingItemTotal();
    }

    public function toggleEditingModifierOption(int $modifierGroupId, int $modifierOptionId): void
    {
        $group = $this->findEditingModifierGroup($modifierGroupId);
        $option = $group === null ? null : $this->findEditingModifierOption($group, $modifierOptionId);

        if ($group === null || $option === null) {
            return;
        }

        $selected = $this->selectedEditingOptionIdsForGroup($modifierGroupId);

        if (in_array($modifierOptionId, $selected, true)) {
            $this->editingModifierOptions[$modifierGroupId] = array_values(array_filter(
                $selected,
                fn (int $selectedOptionId): bool => $selectedOptionId !== $modifierOptionId,
            ));
            $this->refreshEditingItemTotal();

            return;
        }

        $maxSelect = max(0, (int) $group['max_select']);

        if ($maxSelect === 0) {
            return;
        }

        if ($maxSelect === 1) {
            $this->editingModifierOptions[$modifierGroupId] = [$modifierOptionId];
            $this->refreshEditingItemTotal();

            return;
        }

        if (count($selected) >= $maxSelect) {
            return;
        }

        $selected[] = $modifierOptionId;
        $this->editingModifierOptions[$modifierGroupId] = $selected;
        $this->refreshEditingItemTotal();
    }

    public function updateItem(): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        if ($this->editingItemId === null) {
            return;
        }

        $validated = $this->validate($this->editingDraftItemRules());
        $this->editingQuantity = (int) $validated['editingQuantity'];
        $this->editingComment = (string) ($validated['editingComment'] ?? '');
        $this->editingModifierOptions = $validated['editingModifierOptions'] ?? [];

        $guest = $this->currentActiveGuest();
        $draftOrderItem = $this->editableDraftOrderItem($this->editingItemId);

        if (! $guest instanceof TableSessionGuest || ! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_item', __('guest.cart.edit_requires_active_guest'));

            return;
        }

        try {
            $this->updateGuestDraftOrderItem->handle(
                draftOrderItem: $draftOrderItem,
                guest: $guest,
                quantity: (int) $this->editingQuantity,
                selectedModifierOptions: $this->editingModifierOptions,
                menuItemVariantId: $this->editingItemVariantId === '' ? null : (int) $this->editingItemVariantId,
                comment: $this->editingComment,
                languageCode: $this->language,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->feedbackMessage = __('guest.cart.item_updated');
        $this->closeEditItem();
        $this->refreshDraft();
    }

    public function deleteItem(int $itemId): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        $guest = $this->currentActiveGuest();
        $draftOrderItem = $this->editableDraftOrderItem($itemId);

        if (! $guest instanceof TableSessionGuest || ! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_item', __('guest.cart.remove_requires_active_guest'));

            return;
        }

        try {
            $this->deleteGuestDraftOrderItem->handle($draftOrderItem, $guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        if ($this->editingItemId === $itemId) {
            $this->closeEditItem();
        }

        $this->feedbackMessage = __('guest.cart.item_removed');
        $this->refreshDraft();
    }

    public function render(): View
    {
        $this->applyLocale();

        return view('livewire.public-qr.draft-order');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function editingDraftItemRules(): array
    {
        return [
            ...RestaurantValidationRules::quantity('editingQuantity'),
            ...RestaurantValidationRules::guestComment('editingComment'),
            ...RestaurantValidationRules::selectedModifierOptions('editingModifierOptions'),
            'editingItemVariantId' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function applyLocale(): void
    {
        App::setLocale($this->language);
    }

    /**
     * @return Collection<int, TableSessionGuest>
     */
    private function activeGuests(): Collection
    {
        return $this->publicQrQueries->activeGuestsForDraft($this->tableSessionId);
    }

    private function draftOrder(bool $includeOrderStatus = true): ?DraftOrderModel
    {
        return $this->publicQrQueries->draftOrderWithCart($this->tableSessionId, $includeOrderStatus);
    }

    private function confirmedOrdersTotalCents(): int
    {
        return $this->publicQrQueries->confirmedOrdersTotalCents($this->tableSessionId);
    }

    private function tableSessionForBillState(): ?TableSession
    {
        return $this->publicQrQueries->statusTableSession($this->tableSessionId);
    }

    /**
     * @return list<array{guest_id: int, guest_name: string, total_cents: int}>
     */
    private function confirmedOrderItemGuestTotals(): array
    {
        return $this->publicQrQueries->confirmedOrderItemGuestTotals($this->tableSessionId);
    }

    private function openDraftTotalCents(?DraftOrderModel $draftOrder, int $draftTotalCents): int
    {
        if (! $draftOrder instanceof DraftOrderModel) {
            return 0;
        }

        return $draftOrder->status === DraftOrderStatus::ConvertedToOrder ? 0 : $draftTotalCents;
    }

    /**
     * @return Collection<int, KitchenTicketItem>
     */
    private function orderTicketItems(?DraftOrderModel $draftOrder): Collection
    {
        if ($draftOrder?->order === null) {
            return collect();
        }

        return $draftOrder->order
            ->kitchenTickets
            ->flatMap(fn ($ticket): Collection => $ticket->items)
            ->values();
    }

    /**
     * @param  Collection<int, KitchenTicketItem>  $ticketItems
     * @return array{value: string, label: string, tone: string}
     */
    private function guestServiceStatus(?DraftOrderModel $draftOrder, ?OrderStatus $orderStatus, Collection $ticketItems): array
    {
        if (! $draftOrder instanceof DraftOrderModel || $draftOrder->status !== DraftOrderStatus::ConvertedToOrder) {
            return ['value' => '', 'label' => '', 'tone' => 'zinc'];
        }

        if ($orderStatus === OrderStatus::Served || ($ticketItems->isNotEmpty() && $ticketItems->every(
            fn (KitchenTicketItem $item): bool => $item->served_at !== null,
        ))) {
            return ['value' => 'served', 'label' => __('guest.statuses.service.served'), 'tone' => 'sky'];
        }

        if ($orderStatus === OrderStatus::Ready || ($ticketItems->isNotEmpty() && $ticketItems->every(
            fn (KitchenTicketItem $item): bool => $this->ticketItemStatus($item) === KitchenTicketItemStatus::Ready,
        ))) {
            return ['value' => 'ready', 'label' => __('guest.statuses.service.ready'), 'tone' => 'emerald'];
        }

        if ($orderStatus === OrderStatus::InProgress || $ticketItems->contains(
            fn (KitchenTicketItem $item): bool => in_array($this->ticketItemStatus($item), [
                KitchenTicketItemStatus::InProgress,
                KitchenTicketItemStatus::Ready,
            ], true),
        )) {
            return ['value' => 'cooking', 'label' => __('guest.statuses.service.cooking'), 'tone' => 'amber'];
        }

        if (in_array($orderStatus, [OrderStatus::ConfirmedByWaiter, OrderStatus::SentToKitchenBar], true)) {
            return ['value' => 'accepted', 'label' => __('guest.statuses.service.accepted'), 'tone' => 'emerald'];
        }

        return ['value' => '', 'label' => '', 'tone' => 'zinc'];
    }

    private function ticketItemStatus(KitchenTicketItem $item): KitchenTicketItemStatus
    {
        return $item->status;
    }

    private function draftOrderForSending(): ?DraftOrderModel
    {
        return $this->publicQrQueries->draftOrderForSending($this->tableSessionId);
    }

    private function currentActiveGuest(): ?TableSessionGuest
    {
        if ($this->currentGuestId < 1 || $this->tableSessionId < 1) {
            return null;
        }

        $guestToken = $this->guestTokenFromCurrentState();

        if ($guestToken === null) {
            return null;
        }

        return $this->publicQrQueries->activeGuest($this->currentGuestId, $this->tableSessionId, $guestToken);
    }

    private function editableDraftOrderItem(int $itemId): ?DraftOrderItem
    {
        return $this->publicQrQueries->editableDraftOrderItem(
            $itemId,
            $this->currentGuestId,
            $this->tableSessionId,
        );
    }

    /**
     * @return list<string>
     */
    private function modifierSummary(mixed $selectedModifiers): array
    {
        if (! is_array($selectedModifiers)) {
            return [];
        }

        return collect($selectedModifiers)
            ->map(function (mixed $modifier): ?string {
                if (! is_array($modifier)) {
                    return null;
                }

                $optionName = $modifier['option_name'] ?? null;

                return is_string($optionName) && $optionName !== '' ? $optionName : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, is_required: bool, min_select: int, max_select: int, options: list<array{id: int, name: string, price_delta_cents: int, formatted_price_delta: string}>}>
     */
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
                        'formatted_price_delta' => MoneyFormatter::formatSignedCents($modifierOption->price_delta_cents, $this->currency),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, price_cents: int, formatted_price: string}>
     */
    private function variantPayloadFor(?MenuItem $menuItem): array
    {
        if (! $menuItem instanceof MenuItem) {
            return [];
        }

        return $this->publicQrQueries->localizedAvailableVariants($menuItem, $this->language, $this->currency);
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

            if (! in_array($optionId, $availableOptionIds, true)) {
                return;
            }

            $selectedOptions[$groupId][] = $optionId;
        });

        foreach ($selectedOptions as $groupId => $optionIds) {
            $selectedOptions[$groupId] = array_values(array_unique($optionIds));
        }

        return $selectedOptions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findEditingModifierGroup(int $modifierGroupId): ?array
    {
        foreach ($this->editingModifierGroups as $modifierGroup) {
            if ((int) $modifierGroup['id'] === $modifierGroupId) {
                return $modifierGroup;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $modifierGroup
     * @return array<string, mixed>|null
     */
    private function findEditingModifierOption(array $modifierGroup, int $modifierOptionId): ?array
    {
        foreach ($modifierGroup['options'] as $modifierOption) {
            if ((int) $modifierOption['id'] === $modifierOptionId) {
                return $modifierOption;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function selectedEditingOptionIdsForGroup(int $modifierGroupId): array
    {
        $group = $this->findEditingModifierGroup($modifierGroupId);

        if ($group === null) {
            return [];
        }

        $availableOptionIds = collect($group['options'])
            ->pluck('id')
            ->map(fn (mixed $optionId): int => (int) $optionId)
            ->all();

        return collect($this->editingModifierOptions[$modifierGroupId] ?? [])
            ->map(fn (mixed $optionId): int => (int) $optionId)
            ->filter(fn (int $optionId): bool => in_array($optionId, $availableOptionIds, true))
            ->unique()
            ->values()
            ->all();
    }

    private function refreshEditingItemTotal(): void
    {
        $modifierTotalCents = $this->editingModifierGroups === []
            ? MoneyFormatter::decimalToCents($this->editingModifierTotal)
            : 0;

        foreach ($this->editingModifierGroups as $modifierGroup) {
            $selectedOptionIds = $this->selectedEditingOptionIdsForGroup((int) $modifierGroup['id']);

            foreach ($modifierGroup['options'] as $modifierOption) {
                if (in_array((int) $modifierOption['id'], $selectedOptionIds, true)) {
                    $modifierTotalCents += (int) $modifierOption['price_delta_cents'];
                }
            }
        }

        $quantity = max(1, min(99, (int) $this->editingQuantity));
        $unitPriceCents = MoneyFormatter::decimalToCents($this->editingUnitPrice);
        $this->editingItemTotal = MoneyFormatter::centsToDecimal(max(0, $unitPriceCents + $modifierTotalCents) * $quantity);
    }

    private function guestTokenFromCurrentState(): ?string
    {
        if ($this->publicToken === '') {
            return null;
        }

        $guestToken = request()->cookie($this->guestTokenCookieName($this->publicToken));

        if (is_string($guestToken) && strlen($guestToken) === 64) {
            return $guestToken;
        }

        $guestToken = session('guest_entries.'.$this->publicToken.'.guest_token');

        if (! is_string($guestToken) || strlen($guestToken) !== 64) {
            return null;
        }

        return $guestToken;
    }

    private function guestTokenCookieName(string $publicToken): string
    {
        return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
    }

    private function showValidationException(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($field, (string) collect($messages)->first());
        }
    }
}
