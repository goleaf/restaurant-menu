<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Orders\TransitionTableOrdersAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\TableSessions\CanRequestBillForTableSessionAction;
use App\Actions\TableSessions\TransitionTableSessionStatusAction;
use App\Enums\AuditLogAction;
use App\Enums\BusinessRuleCode;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\ManualPayment;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Support\MoneyFormatter;
use App\Support\PlainText;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordManualPaymentAction
{
    public function __construct(
        private readonly BuildManualPaymentSummaryAction $buildPaymentSummary,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly TransitionTableOrdersAction $transitionTableOrders,
        private readonly TransitionTableSessionStatusAction $transitionTableSessionStatus,
        private readonly CanRequestBillForTableSessionAction $canRequestBill,
    ) {}

    public function recordTable(
        TableSession $tableSession,
        User $recordedBy,
        ManualPaymentMethod|string $paymentMethod,
        ?string $note = null,
        string|int|null $tipsAmount = null,
    ): ManualPayment {
        return $this->record(
            tableSession: $tableSession,
            recordedBy: $recordedBy,
            paymentMethod: $paymentMethod,
            scope: ManualPaymentScope::Table,
            guest: null,
            note: $note,
            tipsAmount: $tipsAmount,
        );
    }

    public function recordGuest(
        TableSession $tableSession,
        TableSessionGuest $guest,
        User $recordedBy,
        ManualPaymentMethod|string $paymentMethod,
        ?string $note = null,
        string|int|null $tipsAmount = null,
    ): ManualPayment {
        return $this->record(
            tableSession: $tableSession,
            recordedBy: $recordedBy,
            paymentMethod: $paymentMethod,
            scope: ManualPaymentScope::Guest,
            guest: $guest,
            note: $note,
            tipsAmount: $tipsAmount,
        );
    }

    private function record(
        TableSession $tableSession,
        User $recordedBy,
        ManualPaymentMethod|string $paymentMethod,
        ManualPaymentScope $scope,
        ?TableSessionGuest $guest,
        ?string $note,
        string|int|null $tipsAmount,
    ): ManualPayment {
        return DB::transaction(function () use ($tableSession, $recordedBy, $paymentMethod, $scope, $guest, $note, $tipsAmount): ManualPayment {
            $tableSession = $this->reloadTableSession($tableSession);
            $method = $this->normalizePaymentMethod($paymentMethod);

            $this->ensureCanRecord($tableSession, $recordedBy);

            $summary = $this->buildPaymentSummary->handle($tableSession);
            $breakdown = $this->paymentBreakdownCents($summary, $scope, $guest, $tipsAmount);

            $manualPayment = new ManualPayment;
            $manualPayment->forceFill([
                'branch_id' => $tableSession->branch_id,
                'service_point_id' => $tableSession->service_point_id,
                'table_session_id' => $tableSession->id,
                'table_session_guest_id' => $guest?->id,
                'recorded_by_user_id' => $recordedBy->id,
                'scope' => $scope,
                'payment_method' => $method,
                'covered_subtotal_cents' => $breakdown['covered_subtotal_cents'],
                'service_charge_basis_points' => $breakdown['service_charge_basis_points'],
                'service_charge_cents' => $breakdown['service_charge_cents'],
                'tips_cents' => $breakdown['tips_cents'],
                'amount_cents' => $breakdown['amount_cents'],
                'currency' => (string) $summary['currency'],
                'guest_name' => $guest?->guest_name,
                'note' => $this->normalizeNote($note),
                'paid_at' => now(),
                'metadata' => [
                    'bill_snapshot' => [
                        'confirmed_total_cents' => (int) $summary['confirmed_total_cents'],
                        'covered_subtotal_cents' => $breakdown['covered_subtotal_cents'],
                        'service_charge_enabled' => (bool) $summary['service_charge_enabled'],
                        'service_charge_basis_points' => $breakdown['service_charge_basis_points'],
                        'service_charge_cents' => $breakdown['service_charge_cents'],
                        'tips_enabled' => (bool) $summary['tips_enabled'],
                        'tips_cents' => $breakdown['tips_cents'],
                        'total_cents' => $breakdown['amount_cents'],
                    ],
                ],
            ])->save();

            $this->syncSessionAndServicePointStatus(
                tableSession: $tableSession,
                recordedBy: $recordedBy,
                remainingCentsAfterPayment: max(0, (int) $summary['remaining_total_cents'] - $breakdown['bill_cents']),
            );

            $this->recordAuditLog->handle(
                action: AuditLogAction::PaymentRecorded,
                entityType: 'manual_payment',
                entityId: $manualPayment->id,
                actorUser: $recordedBy,
                organizationId: $tableSession->branch->organization_id,
                branchId: $manualPayment->branch_id,
                oldValues: [
                    'remaining_total_cents' => $summary['remaining_total_cents'],
                ],
                newValues: [
                    'scope' => $manualPayment->scope,
                    'payment_method' => $manualPayment->payment_method,
                    'covered_subtotal_cents' => $manualPayment->covered_subtotal_cents,
                    'service_charge_basis_points' => $manualPayment->service_charge_basis_points,
                    'service_charge_cents' => $manualPayment->service_charge_cents,
                    'tips_cents' => $manualPayment->tips_cents,
                    'amount_cents' => $manualPayment->amount_cents,
                    'currency' => $manualPayment->currency,
                    'table_session_guest_id' => $manualPayment->table_session_guest_id,
                ],
            );

            return $manualPayment->refresh();
        }, attempts: 3);
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'ended_at',
                'closed_by_user_id',
                'metadata',
            ])
            ->with([
                'branch:id,organization_id',
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'status', 'is_active'])
                    ->lockForUpdate(),
            ])
            ->whereKey($tableSession->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureCanRecord(TableSession $tableSession, User $recordedBy): void
    {
        if (Gate::forUser($recordedBy)->denies('create', [ManualPayment::class, $tableSession->branch])) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::BranchInaccessible,
                'manual_payment',
                __('payments.errors.permission_denied'),
            );
        }

        if (! $tableSession->servicePoint->is_active) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::BranchInaccessible,
                'manual_payment',
                __('payments.errors.service_point_unavailable'),
            );
        }

        if ($tableSession->status === TableSessionStatus::Paid) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::PaymentExceedsRemaining,
                'manual_payment',
                __('payments.messages.session_paid'),
            );
        }

        if ($tableSession->status->isTerminal()) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::SessionClosed,
                'manual_payment',
                __('payments.errors.session_closed'),
            );
        }

        if (! $tableSession->status->allowsPaymentRecording()) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::PaymentNotAllowed,
                'manual_payment',
                __('payments.errors.session_not_payable'),
            );
        }

        if (! $this->canRequestBill->handle($tableSession)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::PaymentNotAllowed,
                'manual_payment',
                __('payments.errors.session_not_payable'),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{covered_subtotal_cents: int, service_charge_cents: int, tips_cents: int, bill_cents: int, amount_cents: int, service_charge_basis_points: int}
     */
    private function paymentBreakdownCents(
        array $summary,
        ManualPaymentScope $scope,
        ?TableSessionGuest $guest,
        string|int|null $tipsAmount,
    ): array {
        if ((bool) $summary['has_open_draft']) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::DraftLocked,
                'manual_payment',
                __('payments.errors.open_draft'),
            );
        }

        if (! (bool) $summary['has_payable_total']) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::PaymentExceedsRemaining,
                'manual_payment',
                __('payments.errors.no_confirmed_orders'),
            );
        }

        $tipsCents = $this->normalizeTipsCents($tipsAmount, (bool) $summary['tips_enabled']);
        $remainingTotalCents = (int) $summary['remaining_total_cents'];

        if ($scope === ManualPaymentScope::Table) {
            $coveredSubtotalCents = (int) $summary['remaining_subtotal_cents'];
            $serviceChargeCents = (int) $summary['remaining_service_charge_cents'];
            $billCents = $remainingTotalCents;
        } else {
            if (! $guest instanceof TableSessionGuest) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::GuestNotActive,
                    'manual_payment',
                    __('payments.errors.guest_required'),
                );
            }

            $balance = collect($summary['guest_balances'])
                ->first(fn (array $guestBalance): bool => (int) $guestBalance['guest_id'] === $guest->id);

            if (! is_array($balance)) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::GuestNotActive,
                    'manual_payment',
                    __('payments.errors.guest_not_found'),
                );
            }

            $coveredSubtotalCents = min((int) $balance['remaining_subtotal_cents'], (int) $summary['remaining_subtotal_cents']);
            $serviceChargeCents = min((int) $balance['remaining_service_charge_cents'], (int) $summary['remaining_service_charge_cents']);
            $billCents = min((int) $balance['remaining_cents'], $remainingTotalCents);
        }

        if ($billCents <= 0) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::PaymentExceedsRemaining,
                'manual_payment',
                __('payments.errors.amount_exceeds_remaining'),
            );
        }

        return [
            'covered_subtotal_cents' => $coveredSubtotalCents,
            'service_charge_cents' => $serviceChargeCents,
            'tips_cents' => $tipsCents,
            'bill_cents' => $billCents,
            'amount_cents' => $billCents + $tipsCents,
            'service_charge_basis_points' => (int) ($summary['service_charge_basis_points'] ?? 0),
        ];
    }

    private function normalizeTipsCents(string|int|null $tipsAmount, bool $tipsEnabled): int
    {
        $tipsCents = $this->decimalToCents($tipsAmount);

        if ($tipsCents < 0 || $tipsCents > 10000000) {
            throw ValidationException::withMessages([
                'tipsAmount' => __('ui.actions.payments.recordmanualpaymentaction.vvedite_poniatnuiu_summu_caev'),
            ]);
        }

        if (! $tipsEnabled && $tipsCents > 0) {
            throw ValidationException::withMessages([
                'tipsAmount' => __('ui.actions.payments.recordmanualpaymentaction.caevye_vykliuceny_v_nastroika'),
            ]);
        }

        return $tipsCents;
    }

    private function syncSessionAndServicePointStatus(
        TableSession $tableSession,
        User $recordedBy,
        int $remainingCentsAfterPayment,
    ): void {
        $metadata = (array) ($tableSession->metadata ?? []);

        if ($remainingCentsAfterPayment === 0) {
            $metadata['paid_at'] = now()->toISOString();
            $metadata['paid_by_user_id'] = $recordedBy->id;

            $this->transitionTableSessionStatus->handle($tableSession, TableSessionStatus::Paid);
            $tableSession->forceFill(['metadata' => $metadata])->save();

            $this->transitionTableOrders->handle(
                tableSession: $tableSession,
                targetStatus: OrderStatus::Paid,
                actorUser: $recordedBy,
                errorField: 'manual_payment',
            );

            $this->updateServicePointStatus->handle($tableSession->servicePoint, ServicePointStatus::Paid);

            return;
        }

        $metadata['last_manual_payment_at'] = now()->toISOString();
        $metadata['last_manual_payment_by_user_id'] = $recordedBy->id;

        $this->transitionTableSessionStatus->handle($tableSession, TableSessionStatus::PaymentRequested);
        $tableSession->forceFill(['metadata' => $metadata])->save();

        $this->transitionTableOrders->handle(
            tableSession: $tableSession,
            targetStatus: OrderStatus::PaymentRequested,
            actorUser: $recordedBy,
            errorField: 'manual_payment',
        );

        $this->updateServicePointStatus->handle($tableSession->servicePoint, ServicePointStatus::PaymentRequested);
    }

    private function normalizeNote(?string $note): ?string
    {
        return PlainText::optional($note, 500);
    }

    private function normalizePaymentMethod(ManualPaymentMethod|string $paymentMethod): ManualPaymentMethod
    {
        if ($paymentMethod instanceof ManualPaymentMethod) {
            return $paymentMethod;
        }

        $validated = Validator::make(
            ['paymentMethod' => $paymentMethod],
            RestaurantValidationRules::paymentMethod('paymentMethod'),
            [
                'paymentMethod.in' => __('payments.errors.method_required'),
                'paymentMethod.required' => __('payments.errors.method_required'),
            ],
        )->validate();

        return ManualPaymentMethod::from((string) $validated['paymentMethod']);
    }

    private function decimalToCents(string|int|null $amount): int
    {
        return MoneyFormatter::decimalToCents($amount);
    }
}
