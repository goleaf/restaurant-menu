<?php

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\TableSessions\RequestBillForTableSessionAction;
use App\Actions\TableSessions\ToggleTableSessionGuestReadyAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class DraftTotals extends Component
{
    public int $tableSessionId = 0;

    public int $currentGuestId = 0;

    public string $publicToken = '';

    public string $currency = 'EUR';

    public int $pollingIntervalSeconds = 1;

    /**
     * @var list<array{guest_id: int, guest_name: string, total: string, draft_total: string, confirmed_total: string, has_draft_total: bool, has_confirmed_total: bool, is_current_guest: bool, is_ready: bool}>
     */
    public array $guestTotals = [];

    public string $currentDraftTotalAmount = '0.00';

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

    public bool $currentGuestReady = false;

    public bool $allGuestsReady = false;

    public int $activeGuestCount = 0;

    public int $readyGuestCount = 0;

    public string $feedbackMessage = '';

    public function mount(
        int $tableSessionId,
        int $currentGuestId,
        string $currency = 'EUR',
        string $publicToken = '',
        int $pollingIntervalSeconds = 1,
    ): void {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->currency = $currency;
        $this->publicToken = $publicToken;
        $this->pollingIntervalSeconds = GetBranchPollingIntervalAction::normalize($pollingIntervalSeconds);

        $this->refreshTotals();
    }

    public function refreshTotals(): void
    {
        $guests = $this->activeGuests();
        $draftOrder = $this->draftOrder();
        $draftItems = $draftOrder?->items ?? collect();
        $tableSession = $this->tableSessionForBillState();
        $guestTotals = [];
        $draftTotalCents = 0;
        $confirmedOrdersTotalCents = $this->confirmedOrdersTotalCents();
        $confirmedGuestTotals = $this->confirmedOrderItemGuestTotals();

        $this->billRequested = $tableSession?->status === TableSessionStatus::PaymentRequested;
        $this->canEditDraft = $draftOrder === null || $draftOrder->status === DraftOrderStatus::Draft;
        $this->activeGuestCount = $guests->count();
        $this->readyGuestCount = $guests->filter(fn (TableSessionGuest $guest): bool => $guest->ready_at !== null)->count();
        $this->allGuestsReady = $this->activeGuestCount > 0 && $this->readyGuestCount === $this->activeGuestCount;
        $this->currentGuestReady = false;
        $this->canToggleReadyStatus = false;
        $this->canSendDraftToWaiter = false;
        $this->canRequestBill = false;

        $guests->each(function (TableSessionGuest $guest) use (&$guestTotals, $tableSession): void {
            $isCurrentGuest = $guest->id === $this->currentGuestId;
            $isReady = $guest->ready_at !== null;

            if ($isCurrentGuest) {
                $this->currentGuestReady = $isReady;
                $this->canToggleReadyStatus = $this->publicToken !== '' && $this->canEditDraft;
                $this->canSendDraftToWaiter = $this->publicToken !== '' && $this->canEditDraft;
                $this->canRequestBill = $this->publicToken !== ''
                    && $tableSession instanceof TableSession
                    && ! $this->billRequested
                    && ! in_array($tableSession->status, [
                        TableSessionStatus::Paid,
                        TableSessionStatus::Closed,
                        TableSessionStatus::Cancelled,
                    ], true);
            }

            $guestTotals[$guest->id] = [
                'guest_id' => $guest->id,
                'guest_name' => $guest->guest_name,
                'draft_total_cents' => 0,
                'confirmed_total_cents' => 0,
                'total_cents' => 0,
                'is_current_guest' => $isCurrentGuest,
                'is_ready' => $isReady,
            ];
        });

        foreach ($confirmedGuestTotals as $index => $confirmedGuestTotal) {
            $guestId = (int) $confirmedGuestTotal['guest_id'];
            $guestKey = $guestId > 0 ? $guestId : 'confirmed-'.$index;
            $confirmedTotalCents = (int) $confirmedGuestTotal['total_cents'];

            if (! isset($guestTotals[$guestKey])) {
                $guestTotals[$guestKey] = [
                    'guest_id' => $guestId > 0 ? $guestId : -($index + 1),
                    'guest_name' => $confirmedGuestTotal['guest_name'],
                    'draft_total_cents' => 0,
                    'confirmed_total_cents' => 0,
                    'total_cents' => 0,
                    'is_current_guest' => $guestId === $this->currentGuestId,
                    'is_ready' => false,
                ];
            }

            $guestTotals[$guestKey]['confirmed_total_cents'] += $confirmedTotalCents;
            $guestTotals[$guestKey]['total_cents'] += $confirmedTotalCents;
        }

        $draftItems->each(function (DraftOrderItem $item) use (&$guestTotals, &$draftTotalCents): void {
            $itemTotalCents = self::decimalToCents($item->total_price);
            $draftTotalCents += $itemTotalCents;
            $guestId = (int) $item->table_session_guest_id;
            $guestName = $item->guest?->guest_name ?? __('Гость');

            if (! isset($guestTotals[$guestId])) {
                $guestTotals[$guestId] = [
                    'guest_id' => $guestId,
                    'guest_name' => $guestName,
                    'draft_total_cents' => 0,
                    'confirmed_total_cents' => 0,
                    'total_cents' => 0,
                    'is_current_guest' => $guestId === $this->currentGuestId,
                    'is_ready' => false,
                ];
            }

            $guestTotals[$guestId]['draft_total_cents'] += $itemTotalCents;
            $guestTotals[$guestId]['total_cents'] += $itemTotalCents;
        });

        $openDraftTotalCents = $this->openDraftTotalCents($draftOrder, $draftTotalCents);

        $this->guestTotals = collect($guestTotals)
            ->sortBy(fn (array $guestTotal): string => mb_strtolower($guestTotal['guest_name']))
            ->map(fn (array $guestTotal): array => [
                'guest_id' => $guestTotal['guest_id'],
                'guest_name' => $guestTotal['guest_name'],
                'total' => self::centsToDecimal($guestTotal['total_cents']),
                'draft_total' => self::centsToDecimal($guestTotal['draft_total_cents']),
                'confirmed_total' => self::centsToDecimal($guestTotal['confirmed_total_cents']),
                'has_draft_total' => $guestTotal['draft_total_cents'] > 0,
                'has_confirmed_total' => $guestTotal['confirmed_total_cents'] > 0,
                'is_current_guest' => $guestTotal['is_current_guest'],
                'is_ready' => $guestTotal['is_ready'],
            ])
            ->values()
            ->all();

        $this->currentDraftTotalAmount = self::centsToDecimal($openDraftTotalCents);
        $this->confirmedOrdersTotalAmount = self::centsToDecimal($confirmedOrdersTotalCents);
        $this->tableTotalAmount = self::centsToDecimal($confirmedOrdersTotalCents + $openDraftTotalCents);
        $this->hasConfirmedOrders = $confirmedOrdersTotalCents > 0;
        $this->itemCount = $draftOrder instanceof DraftOrder && $draftOrder->status === DraftOrderStatus::Draft
            ? $draftItems->count()
            : 0;
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

        $this->refreshTotals();
    }

    public function requestBill(RequestBillForTableSessionAction $requestBill): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        $guest = $this->currentActiveGuest();

        if (! $guest instanceof TableSessionGuest) {
            $this->addError('bill_request', __('Только активный гость за этим столом может попросить счёт.'));

            return;
        }

        $tableSession = TableSession::query()
            ->select(['id'])
            ->whereKey($this->tableSessionId)
            ->first();

        if (! $tableSession instanceof TableSession) {
            $this->addError('bill_request', __('Сессия стола не найдена.'));

            return;
        }

        try {
            $requestBill->handle($tableSession, $guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->sendNeedsReadyConfirmation = false;
        $this->feedbackMessage = __('Официант получил просьбу принести счёт.');
        $this->refreshTotals();
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

        if (! $guest instanceof TableSessionGuest || ! $draftOrder instanceof DraftOrder) {
            $this->addError('send_draft', __('Только активный гость за этим столом может отправить заказ официанту.'));

            return;
        }

        try {
            app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->sendNeedsReadyConfirmation = false;
        $this->feedbackMessage = __('Заказ отправлен официанту.');
        $this->refreshTotals();
    }

    public function cancelSendDraftConfirmation(): void
    {
        $this->sendNeedsReadyConfirmation = false;
    }

    public function render(): View
    {
        return view('livewire.public-qr.draft-totals');
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

    private function draftOrder(): ?DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
            ])
            ->with([
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'draft_order_id',
                        'table_session_guest_id',
                        'total_price',
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
            ->latest('id')
            ->first();
    }

    private function tableSessionForBillState(): ?TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'status',
            ])
            ->whereKey($this->tableSessionId)
            ->first();
    }

    private function confirmedOrdersTotalCents(): int
    {
        return Order::query()
            ->select(['id', 'table_session_id', 'status', 'total_price'])
            ->where('table_session_id', $this->tableSessionId)
            ->whereNotIn('status', [OrderStatus::Cancelled->value])
            ->get()
            ->sum(fn (Order $order): int => self::decimalToCents($order->total_price));
    }

    /**
     * @return list<array{guest_id: int, guest_name: string, total_cents: int}>
     */
    private function confirmedOrderItemGuestTotals(): array
    {
        return OrderItem::query()
            ->select([
                'id',
                'order_id',
                'table_session_guest_id',
                'guest_name',
                'total_price',
            ])
            ->with(['guest' => fn ($query) => $query->select(['id', 'guest_name'])])
            ->whereHas('order', function ($query): void {
                $query
                    ->where('table_session_id', $this->tableSessionId)
                    ->whereNotIn('status', [OrderStatus::Cancelled->value]);
            })
            ->orderBy('id')
            ->get()
            ->groupBy(function (OrderItem $item): string {
                if ((int) $item->table_session_guest_id > 0) {
                    return 'guest-'.$item->table_session_guest_id;
                }

                return 'snapshot-'.$item->guest_name;
            })
            ->map(function (Collection $items): array {
                /** @var OrderItem $firstItem */
                $firstItem = $items->first();

                return [
                    'guest_id' => (int) $firstItem->table_session_guest_id,
                    'guest_name' => $firstItem->guest?->guest_name ?? $firstItem->guest_name ?? __('Гость'),
                    'total_cents' => $items->sum(fn (OrderItem $item): int => self::decimalToCents($item->total_price)),
                ];
            })
            ->values()
            ->all();
    }

    private function openDraftTotalCents(?DraftOrder $draftOrder, int $draftTotalCents): int
    {
        if (! $draftOrder instanceof DraftOrder) {
            return 0;
        }

        return $draftOrder->status === DraftOrderStatus::ConvertedToOrder ? 0 : $draftTotalCents;
    }

    private function draftOrderForSending(): ?DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->where('status', DraftOrderStatus::Draft->value)
            ->latest('id')
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
