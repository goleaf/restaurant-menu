<?php

declare(strict_types=1);

namespace App\Livewire\Waiter\TableDetail;

use App\Actions\Payments\RecordManualPaymentAction;
use App\Actions\TableSessions\CloseTableSessionAction;
use App\Models\TableSessionGuest;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class Payment extends TableDetailSection
{
    /**
     * @var array<string, mixed>
     */
    public array $payment = [];

    public string $paymentFeedbackMessage = '';

    public string $paymentMethod = 'cash';

    public string $paymentNote = '';

    public string $tipsAmount = '0.00';

    public string $closeTableConfirmation = '';

    /**
     * @param  array<string, mixed>  $initialPayment
     */
    public function mount(int $tableSessionId, array $initialPayment = []): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->authorizeViewableTableSession();
        $this->payment = $initialPayment === []
            ? $this->paymentPayload($this->freshViewableTablePayload())
            : $initialPayment;
    }

    public function refreshPayment(): void
    {
        $this->payment = $this->paymentPayload($this->freshViewableTablePayload());
    }

    public function recordTablePayment(RecordManualPaymentAction $recordManualPayment): void
    {
        $this->resetValidation();
        $this->paymentFeedbackMessage = '';
        $tableSession = $this->authorizePaymentTableSession();
        $validated = $this->validate($this->manualPaymentRules(), $this->manualPaymentMessages());

        try {
            $recordManualPayment->recordTable(
                tableSession: $tableSession,
                recordedBy: $this->currentUser(),
                paymentMethod: (string) $validated['paymentMethod'],
                note: (string) ($validated['paymentNote'] ?? ''),
                tipsAmount: (string) $validated['tipsAmount'],
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->paymentNote = '';
        $this->tipsAmount = '0.00';
        $this->paymentFeedbackMessage = __('payments.messages.payment_recorded');
        $this->refreshPayment();
        $this->dispatch('waiter-table-payment-updated');
    }

    public function recordGuestPayment(int $guestId, RecordManualPaymentAction $recordManualPayment): void
    {
        $this->resetValidation();
        $this->paymentFeedbackMessage = '';
        $tableSession = $this->authorizePaymentTableSession();
        $validated = $this->validate($this->manualPaymentRules(), $this->manualPaymentMessages());
        $guest = $this->paymentGuestForCurrentTable($guestId);

        if (! $guest instanceof TableSessionGuest) {
            $this->addError('manual_payment', __('payments.errors.guest_not_found'));

            return;
        }

        try {
            $recordManualPayment->recordGuest(
                tableSession: $tableSession,
                guest: $guest,
                recordedBy: $this->currentUser(),
                paymentMethod: (string) $validated['paymentMethod'],
                note: (string) ($validated['paymentNote'] ?? ''),
                tipsAmount: (string) $validated['tipsAmount'],
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->paymentNote = '';
        $this->tipsAmount = '0.00';
        $this->paymentFeedbackMessage = __('payments.messages.payment_recorded');
        $this->refreshPayment();
        $this->dispatch('waiter-table-payment-updated');
    }

    public function closePaidSession(CloseTableSessionAction $closeTableSession): void
    {
        $this->closeTableSession($closeTableSession);
    }

    public function closeTableSession(CloseTableSessionAction $closeTableSession): void
    {
        $this->resetValidation();
        $this->paymentFeedbackMessage = '';

        $serverPayment = $this->paymentPayload($this->freshViewableTablePayload());
        $this->payment = $serverPayment;

        if ((bool) data_get($serverPayment, 'session.close_requires_warning')) {
            $this->validate([
                'closeTableConfirmation' => ['required', 'string', 'in:CLOSE'],
            ], [
                'closeTableConfirmation.required' => __('payments.errors.close_confirmation_required'),
                'closeTableConfirmation.in' => __('payments.errors.close_confirmation_invalid'),
            ]);
        }

        try {
            $closeTableSession->handle($this->authorizeViewableTableSession(), $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->closeTableConfirmation = '';
        $this->paymentFeedbackMessage = __('payments.messages.session_closed');
        Flux::modals()->close();
        $this->refreshPayment();
        $this->dispatch('waiter-table-session-updated');
    }

    public function render(): View
    {
        return view('livewire.waiter.table-detail.payment');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function manualPaymentRules(): array
    {
        return [
            ...RestaurantValidationRules::paymentMethod('paymentMethod'),
            ...RestaurantValidationRules::paymentNote('paymentNote'),
            ...RestaurantValidationRules::manualPaymentAmount('tipsAmount'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function manualPaymentMessages(): array
    {
        return [
            'paymentMethod.required' => __('payments.errors.method_required'),
            'paymentMethod.in' => __('payments.errors.method_required'),
            'tipsAmount.required' => __('payments.errors.amount_required'),
            'tipsAmount.numeric' => __('payments.errors.amount_invalid'),
            'tipsAmount.min' => __('payments.errors.amount_invalid'),
            'tipsAmount.max' => __('payments.errors.amount_invalid'),
            'tipsAmount.decimal' => __('payments.errors.amount_invalid'),
        ];
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

    /**
     * @param  array<string, mixed>  $table
     * @return array<string, mixed>
     */
    private function paymentPayload(array $table): array
    {
        $payment = data_get($table, 'payment', []);

        return [
            ...(is_array($payment) ? $payment : []),
            'session' => data_get($table, 'session', []),
        ];
    }
}
