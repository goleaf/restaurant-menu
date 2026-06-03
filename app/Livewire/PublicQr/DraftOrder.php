<?php

namespace App\Livewire\PublicQr;

use App\Actions\DraftOrders\DeleteGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\DraftOrders\UpdateGuestDraftOrderItemAction;
use App\Actions\TableSessions\ToggleTableSessionGuestReadyAction;
use App\Enums\DraftOrderStatus;
use App\Enums\TableSessionGuestStatus;
use App\Models\DraftOrder as DraftOrderModel;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSessionGuest;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class DraftOrder extends Component
{
    public int $tableSessionId = 0;

    public int $currentGuestId = 0;

    public string $publicToken = '';

    public string $currency = 'EUR';

    /**
     * @var list<array{id: int, guest_id: int, guest_name: string, item_name: string, quantity: int, unit_price: string, modifier_total: string, unit_total_price: string, total_price: string, modifiers: list<string>, comment: string|null, is_current_guest: bool, can_edit: bool}>
     */
    public array $items = [];

    /**
     * @var list<array{guest_id: int, guest_name: string, total: string, is_current_guest: bool, is_ready: bool, items: list<array{id: int, guest_id: int, guest_name: string, item_name: string, quantity: int, unit_price: string, modifier_total: string, unit_total_price: string, total_price: string, modifiers: list<string>, comment: string|null, is_current_guest: bool, can_edit: bool}>}>
     */
    public array $guestSections = [];

    /**
     * @var list<array{guest_id: int, guest_name: string, total: string, is_current_guest: bool}>
     */
    public array $guestTotals = [];

    public string $totalAmount = '0.00';

    public int $itemCount = 0;

    public bool $canEditDraft = true;

    public bool $canToggleReadyStatus = false;

    public bool $canSendDraftToWaiter = false;

    public bool $sendNeedsReadyConfirmation = false;

    public ?string $draftStatusValue = null;

    public string $draftStatusLabel = '';

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

    public function mount(int $tableSessionId, int $currentGuestId, string $currency = 'EUR', string $publicToken = ''): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->currency = $currency;
        $this->publicToken = $publicToken;

        $this->refreshDraft();
    }

    public function refreshDraft(): void
    {
        $guests = $this->activeGuests();
        $draftOrder = $this->draftOrder();
        $draftItems = $draftOrder?->items ?? collect();
        $guestSections = [];
        $totalCents = 0;

        $this->draftStatusValue = $draftOrder?->status?->value;
        $this->draftStatusLabel = $draftOrder?->status?->label() ?? '';
        $this->rejectionReason = $draftOrder?->rejection_reason;
        $this->canEditDraft = $draftOrder === null || $draftOrder->status === DraftOrderStatus::Draft;
        $this->activeGuestCount = $guests->count();
        $this->readyGuestCount = $guests->filter(fn (TableSessionGuest $guest): bool => $guest->ready_at !== null)->count();
        $this->allGuestsReady = $this->activeGuestCount > 0 && $this->readyGuestCount === $this->activeGuestCount;
        $this->currentGuestReady = false;
        $this->canToggleReadyStatus = false;
        $this->canSendDraftToWaiter = false;

        $guests->each(function (TableSessionGuest $guest) use (&$guestSections): void {
            $isCurrentGuest = $guest->id === $this->currentGuestId;
            $isReady = $guest->ready_at !== null;

            if ($isCurrentGuest) {
                $this->currentGuestReady = $isReady;
                $this->canToggleReadyStatus = $this->publicToken !== '' && $this->canEditDraft;
                $this->canSendDraftToWaiter = $this->publicToken !== '' && $this->canEditDraft;
            }

            $guestSections[$guest->id] = [
                'guest_id' => $guest->id,
                'guest_name' => $guest->guest_name,
                'total_cents' => 0,
                'is_current_guest' => $isCurrentGuest,
                'is_ready' => $isReady,
                'items' => [],
            ];
        });

        $items = $draftItems
            ->map(function (DraftOrderItem $item) use (&$guestSections, &$totalCents): array {
                $itemTotalCents = self::decimalToCents($item->total_price);
                $unitTotalCents = max(0, self::decimalToCents($item->unit_price) + self::decimalToCents($item->modifier_total));
                $totalCents += $itemTotalCents;
                $guestId = (int) $item->table_session_guest_id;
                $guestName = $item->guest?->guest_name ?? __('Гость');

                if (! isset($guestSections[$guestId])) {
                    $guestSections[$guestId] = [
                        'guest_id' => $guestId,
                        'guest_name' => $guestName,
                        'total_cents' => 0,
                        'is_current_guest' => $guestId === $this->currentGuestId,
                        'is_ready' => false,
                        'items' => [],
                    ];
                }

                $guestSections[$guestId]['total_cents'] += $itemTotalCents;

                $isCurrentGuest = $item->table_session_guest_id === $this->currentGuestId;

                $itemPayload = [
                    'id' => $item->id,
                    'guest_id' => $guestId,
                    'guest_name' => $guestName,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'modifier_total' => $item->modifier_total,
                    'unit_total_price' => self::centsToDecimal($unitTotalCents),
                    'total_price' => $item->total_price,
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
                'total' => self::centsToDecimal($guestSection['total_cents']),
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

        $this->totalAmount = self::centsToDecimal($totalCents);
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
            $this->addError('ready_status', __('Только активный гость за этим столом может менять готовность.'));

            return;
        }

        try {
            $guest = app(ToggleTableSessionGuestReadyAction::class)->handle($guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->feedbackMessage = $guest->ready_at === null
            ? __('Готовность снята.')
            : __('Вы отметили готовность.');

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
            $this->addError('send_draft', __('Только активный гость за этим столом может отправить заказ официанту.'));

            return;
        }

        try {
            app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->closeEditItem();
        $this->sendNeedsReadyConfirmation = false;
        $this->feedbackMessage = __('Заказ отправлен официанту.');
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
            $this->addError('draft_item', __('Только активный гость за этим столом может менять позиции.'));

            return;
        }

        $draftOrderItem = $this->editableDraftOrderItem($itemId);

        if (! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_item', __('Можно изменять только свои позиции за этим столом.'));

            return;
        }

        if ($draftOrderItem->draftOrder?->status !== DraftOrderStatus::Draft) {
            $this->addError('draft_order', __('Этот черновик уже отправлен официанту. Изменения заблокированы.'));

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

    public function closeEditItem(): void
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

    public function updatedEditingQuantity(): void
    {
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
            $this->editingModifierOptions[(string) $modifierGroupId] = array_values(array_filter(
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
            $this->editingModifierOptions[(string) $modifierGroupId] = [$modifierOptionId];
            $this->refreshEditingItemTotal();

            return;
        }

        if (count($selected) >= $maxSelect) {
            return;
        }

        $selected[] = $modifierOptionId;
        $this->editingModifierOptions[(string) $modifierGroupId] = array_values($selected);
        $this->refreshEditingItemTotal();
    }

    public function updateItem(): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        if ($this->editingItemId === null) {
            return;
        }

        $guest = $this->currentActiveGuest();
        $draftOrderItem = DraftOrderItem::query()
            ->select(['id'])
            ->whereKey($this->editingItemId)
            ->first();

        if (! $guest instanceof TableSessionGuest || ! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_item', __('Только активный гость за этим столом может менять позиции.'));

            return;
        }

        try {
            app(UpdateGuestDraftOrderItemAction::class)->handle(
                draftOrderItem: $draftOrderItem,
                guest: $guest,
                quantity: (int) $this->editingQuantity,
                selectedModifierOptions: $this->editingModifierOptions,
                comment: $this->editingComment,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->feedbackMessage = __('Позиция обновлена.');
        $this->closeEditItem();
        $this->refreshDraft();
    }

    public function deleteItem(int $itemId): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        $guest = $this->currentActiveGuest();
        $draftOrderItem = DraftOrderItem::query()
            ->select(['id'])
            ->whereKey($itemId)
            ->first();

        if (! $guest instanceof TableSessionGuest || ! $draftOrderItem instanceof DraftOrderItem) {
            $this->addError('draft_item', __('Только активный гость за этим столом может удалять позиции.'));

            return;
        }

        try {
            app(DeleteGuestDraftOrderItemAction::class)->handle($draftOrderItem, $guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        if ($this->editingItemId === $itemId) {
            $this->closeEditItem();
        }

        $this->feedbackMessage = __('Позиция удалена.');
        $this->refreshDraft();
    }

    public function render(): View
    {
        return view('livewire.public-qr.draft-order');
    }

    /**
     * @return Collection<int, TableSessionGuest>
     */
    private function activeGuests(): Collection
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
                'ready_at',
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    private function draftOrder(): ?DraftOrderModel
    {
        return DraftOrderModel::query()
            ->select([
                'id',
                'table_session_id',
                'status',
                'rejection_reason',
            ])
            ->with([
                'items' => fn ($query) => $query
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
                        'created_at',
                    ])
                    ->with([
                        'guest' => fn ($guestQuery) => $guestQuery->select([
                            'id',
                            'guest_name',
                            'status',
                        ]),
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->first();
    }

    private function draftOrderForSending(): ?DraftOrderModel
    {
        return DraftOrderModel::query()
            ->select([
                'id',
                'table_session_id',
                'status',
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->first();
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

        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'ready_at',
                'joined_at',
                'left_at',
            ])
            ->whereKey($this->currentGuestId)
            ->where('table_session_id', $this->tableSessionId)
            ->where('guest_token', $guestToken)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->first();
    }

    private function editableDraftOrderItem(int $itemId): ?DraftOrderItem
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
            ->where('table_session_guest_id', $this->currentGuestId)
            ->first();

        if (! $draftOrderItem instanceof DraftOrderItem
            || $draftOrderItem->draftOrder?->table_session_id !== $this->tableSessionId) {
            return null;
        }

        return $draftOrderItem;
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

            if (! in_array($optionId, $availableOptionIds, true)) {
                return;
            }

            $selectedOptions[(string) $groupId][] = $optionId;
        });

        return collect($selectedOptions)
            ->map(fn (array $optionIds): array => collect($optionIds)->unique()->values()->all())
            ->all();
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

        return collect($this->editingModifierOptions[(string) $modifierGroupId] ?? [])
            ->map(fn (mixed $optionId): int => (int) $optionId)
            ->filter(fn (int $optionId): bool => in_array($optionId, $availableOptionIds, true))
            ->unique()
            ->values()
            ->all();
    }

    private function refreshEditingItemTotal(): void
    {
        $modifierTotalCents = $this->editingModifierGroups === []
            ? self::decimalToCents($this->editingModifierTotal)
            : 0;

        foreach ($this->editingModifierGroups as $modifierGroup) {
            $selectedOptionIds = $this->selectedEditingOptionIdsForGroup((int) $modifierGroup['id']);

            foreach ($modifierGroup['options'] as $modifierOption) {
                if (in_array((int) $modifierOption['id'], $selectedOptionIds, true)) {
                    $modifierTotalCents += self::decimalToCents((string) $modifierOption['price_delta']);
                }
            }
        }

        $quantity = max(1, min(99, (int) $this->editingQuantity));
        $unitPriceCents = self::decimalToCents($this->editingUnitPrice);
        $this->editingItemTotal = self::centsToDecimal(max(0, $unitPriceCents + $modifierTotalCents) * $quantity);
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
