<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\TableSessions\RequestBillForTableSessionAction;
use App\Actions\TableSessions\ToggleTableSessionGuestReadyAction;
use App\Enums\DraftOrderStatus;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Services\PublicQr\PublicQrQueryService;
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Isolate]
class DraftTotals extends Component
{
    private ToggleTableSessionGuestReadyAction $toggleGuestReady;

    private SendDraftOrderToWaiterAction $sendDraftOrderToWaiter;

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

    public function boot(
        ToggleTableSessionGuestReadyAction $toggleGuestReady,
        SendDraftOrderToWaiterAction $sendDraftOrderToWaiter,
        PublicQrQueryService $publicQrQueries,
    ): void {
        $this->toggleGuestReady = $toggleGuestReady;
        $this->sendDraftOrderToWaiter = $sendDraftOrderToWaiter;
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
        $this->applyLocale();

        $this->refreshTotals();
    }

    public function refreshTotals(): void
    {
        $this->applyLocale();

        $guests = $this->activeGuests();
        $draftOrder = $this->draftOrder();
        $draftItems = $draftOrder instanceof DraftOrder ? $draftOrder->items : collect();
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
                $this->canSendDraftToWaiter = $this->publicToken !== '' && $this->canEditDraft && $this->branchCanAcceptOrders;
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
            $itemTotalCents = $item->total_price_cents;
            $draftTotalCents += $itemTotalCents;
            $guestId = (int) $item->table_session_guest_id;
            $guestName = $item->guest->guest_name;

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
                'total' => MoneyFormatter::centsToDecimal($guestTotal['total_cents']),
                'draft_total' => MoneyFormatter::centsToDecimal($guestTotal['draft_total_cents']),
                'confirmed_total' => MoneyFormatter::centsToDecimal($guestTotal['confirmed_total_cents']),
                'has_draft_total' => $guestTotal['draft_total_cents'] > 0,
                'has_confirmed_total' => $guestTotal['confirmed_total_cents'] > 0,
                'is_current_guest' => $guestTotal['is_current_guest'],
                'is_ready' => $guestTotal['is_ready'],
            ])
            ->values()
            ->all();

        $this->currentDraftTotalAmount = MoneyFormatter::centsToDecimal($openDraftTotalCents);
        $this->confirmedOrdersTotalAmount = MoneyFormatter::centsToDecimal($confirmedOrdersTotalCents);
        $this->tableTotalAmount = MoneyFormatter::centsToDecimal($confirmedOrdersTotalCents + $openDraftTotalCents);
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

        $this->refreshTotals();
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

        $this->sendNeedsReadyConfirmation = false;
        $this->feedbackMessage = __('guest.table.bill_requested');
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
            $this->addError('send_draft', __('guest.table.send_requires_active_guest'));

            return;
        }

        try {
            $this->sendDraftOrderToWaiter->handle($draftOrder, $guest);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->sendNeedsReadyConfirmation = false;
        $this->feedbackMessage = __('guest.table.sent_to_waiter');
        $this->refreshTotals();
    }

    public function cancelSendDraftConfirmation(): void
    {
        $this->sendNeedsReadyConfirmation = false;
    }

    public function render(): View
    {
        $this->applyLocale();

        return view('livewire.public-qr.draft-totals');
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

    private function draftOrder(): ?DraftOrder
    {
        return $this->publicQrQueries->draftOrderWithTotals($this->tableSessionId);
    }

    private function tableSessionForBillState(): ?TableSession
    {
        return $this->publicQrQueries->statusTableSession($this->tableSessionId);
    }

    private function confirmedOrdersTotalCents(): int
    {
        return $this->publicQrQueries->confirmedOrdersTotalCents($this->tableSessionId);
    }

    /**
     * @return list<array{guest_id: int, guest_name: string, total_cents: int}>
     */
    private function confirmedOrderItemGuestTotals(): array
    {
        return $this->publicQrQueries->confirmedOrderItemGuestTotals($this->tableSessionId);
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
