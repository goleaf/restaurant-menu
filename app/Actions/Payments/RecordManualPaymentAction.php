<?php

namespace App\Actions\Payments;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\AuditLogAction;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Models\ManualPayment;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordManualPaymentAction
{
    public function __construct(
        private readonly ResolvePaymentAccessibleBranchIdsAction $resolvePaymentAccess,
        private readonly BuildManualPaymentSummaryAction $buildPaymentSummary,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function recordTable(
        TableSession $tableSession,
        User $recordedBy,
        ManualPaymentMethod|string $paymentMethod,
        ?string $note = null,
        string|int|float|null $tipsAmount = null,
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
        string|int|float|null $tipsAmount = null,
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
        string|int|float|null $tipsAmount,
    ): ManualPayment {
        return DB::transaction(function () use ($tableSession, $recordedBy, $paymentMethod, $scope, $guest, $note, $tipsAmount): ManualPayment {
            $tableSession = $this->reloadTableSession($tableSession);
            $method = $this->normalizePaymentMethod($paymentMethod);

            $this->ensureCanRecord($tableSession, $recordedBy);

            $summary = $this->buildPaymentSummary->handle($tableSession);
            $breakdown = $this->paymentBreakdownCents($summary, $scope, $guest, $tipsAmount);

            $manualPayment = ManualPayment::query()->create([
                'branch_id' => $tableSession->branch_id,
                'service_point_id' => $tableSession->service_point_id,
                'table_session_id' => $tableSession->id,
                'table_session_guest_id' => $guest?->id,
                'recorded_by_user_id' => $recordedBy->id,
                'scope' => $scope,
                'payment_method' => $method,
                'covered_subtotal_amount' => $this->formatCents($breakdown['covered_subtotal_cents']),
                'service_charge_percent' => $breakdown['service_charge_percent'],
                'service_charge_amount' => $this->formatCents($breakdown['service_charge_cents']),
                'tips_amount' => $this->formatCents($breakdown['tips_cents']),
                'amount' => $this->formatCents($breakdown['amount_cents']),
                'currency' => (string) $summary['currency'],
                'guest_name' => $guest?->guest_name,
                'note' => $this->normalizeNote($note),
                'paid_at' => now(),
                'metadata' => [
                    'bill_snapshot' => [
                        'confirmed_total' => $this->formatCents((int) $summary['confirmed_total_cents']),
                        'covered_subtotal_amount' => $this->formatCents($breakdown['covered_subtotal_cents']),
                        'service_charge_enabled' => (bool) $summary['service_charge_enabled'],
                        'service_charge_percent' => $breakdown['service_charge_percent'],
                        'service_charge_amount' => $this->formatCents($breakdown['service_charge_cents']),
                        'tips_enabled' => (bool) $summary['tips_enabled'],
                        'tips_amount' => $this->formatCents($breakdown['tips_cents']),
                        'total_amount' => $this->formatCents($breakdown['amount_cents']),
                    ],
                ],
            ]);

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
                organizationId: $tableSession->branch?->organization_id,
                branchId: $manualPayment->branch_id,
                oldValues: [
                    'remaining_total' => $summary['remaining_total'],
                ],
                newValues: [
                    'scope' => $manualPayment->scope,
                    'payment_method' => $manualPayment->payment_method,
                    'covered_subtotal_amount' => $manualPayment->covered_subtotal_amount,
                    'service_charge_percent' => $manualPayment->service_charge_percent,
                    'service_charge_amount' => $manualPayment->service_charge_amount,
                    'tips_amount' => $manualPayment->tips_amount,
                    'amount' => $manualPayment->amount,
                    'currency' => $manualPayment->currency,
                    'table_session_guest_id' => $manualPayment->table_session_guest_id,
                ],
            );

            return $manualPayment->refresh();
        });
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
                'servicePoint' => fn ($query) => $query->select(['id', 'branch_id', 'status', 'is_active']),
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();
    }

    private function ensureCanRecord(TableSession $tableSession, User $recordedBy): void
    {
        if (! $this->resolvePaymentAccess->canManage($recordedBy, (int) $tableSession->branch_id)) {
            throw ValidationException::withMessages([
                'manual_payment' => __('У вас нет права отмечать оплату для этого стола.'),
            ]);
        }

        if (! $tableSession->servicePoint instanceof ServicePoint || ! $tableSession->servicePoint->is_active) {
            throw ValidationException::withMessages([
                'manual_payment' => __('Это место сейчас недоступно. Оплату нельзя отметить.'),
            ]);
        }

        if (in_array($tableSession->status, [
            TableSessionStatus::Paid,
            TableSessionStatus::Closed,
            TableSessionStatus::Cancelled,
        ], true)) {
            throw ValidationException::withMessages([
                'manual_payment' => __('Эта сессия уже оплачена, закрыта или отменена.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{covered_subtotal_cents: int, service_charge_cents: int, tips_cents: int, bill_cents: int, amount_cents: int, service_charge_percent: string}
     */
    private function paymentBreakdownCents(
        array $summary,
        ManualPaymentScope $scope,
        ?TableSessionGuest $guest,
        string|int|float|null $tipsAmount,
    ): array {
        if ((bool) $summary['has_open_draft']) {
            throw ValidationException::withMessages([
                'manual_payment' => __('Сначала завершите текущий черновик заказа: подтвердите, отклоните или верните его гостям.'),
            ]);
        }

        if (! (bool) $summary['has_payable_total']) {
            throw ValidationException::withMessages([
                'manual_payment' => __('У этого стола пока нет подтверждённых заказов для оплаты.'),
            ]);
        }

        $tipsCents = $this->normalizeTipsCents($tipsAmount, (bool) $summary['tips_enabled']);
        $remainingTotalCents = (int) $summary['remaining_total_cents'];

        if ($scope === ManualPaymentScope::Table) {
            $coveredSubtotalCents = (int) $summary['remaining_subtotal_cents'];
            $serviceChargeCents = (int) $summary['remaining_service_charge_cents'];
            $billCents = $remainingTotalCents;
        } else {
            if (! $guest instanceof TableSessionGuest) {
                throw ValidationException::withMessages([
                    'manual_payment' => __('Выберите гостя для отметки оплаты.'),
                ]);
            }

            $balance = collect($summary['guest_balances'])
                ->first(fn (array $guestBalance): bool => (int) $guestBalance['guest_id'] === $guest->id);

            if (! is_array($balance)) {
                throw ValidationException::withMessages([
                    'manual_payment' => __('Гость не найден в этой сессии.'),
                ]);
            }

            $coveredSubtotalCents = min((int) $balance['remaining_subtotal_cents'], (int) $summary['remaining_subtotal_cents']);
            $serviceChargeCents = min((int) $balance['remaining_service_charge_cents'], (int) $summary['remaining_service_charge_cents']);
            $billCents = min((int) $balance['remaining_cents'], $remainingTotalCents);
        }

        if ($billCents <= 0) {
            throw ValidationException::withMessages([
                'manual_payment' => __('Для этой оплаты нет остатка к оплате.'),
            ]);
        }

        return [
            'covered_subtotal_cents' => $coveredSubtotalCents,
            'service_charge_cents' => $serviceChargeCents,
            'tips_cents' => $tipsCents,
            'bill_cents' => $billCents,
            'amount_cents' => $billCents + $tipsCents,
            'service_charge_percent' => $this->formatPercent($summary['service_charge_percent'] ?? '0.00'),
        ];
    }

    private function normalizeTipsCents(string|int|float|null $tipsAmount, bool $tipsEnabled): int
    {
        $tipsCents = $this->decimalToCents($tipsAmount);

        if ($tipsCents < 0 || $tipsCents > 10000000) {
            throw ValidationException::withMessages([
                'tipsAmount' => __('Введите понятную сумму чаевых.'),
            ]);
        }

        if (! $tipsEnabled && $tipsCents > 0) {
            throw ValidationException::withMessages([
                'tipsAmount' => __('Чаевые выключены в настройках филиала.'),
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

            $tableSession->fill([
                'status' => TableSessionStatus::Paid,
                'metadata' => $metadata,
            ])->save();

            if ($tableSession->servicePoint instanceof ServicePoint) {
                $this->updateServicePointStatus->handle($tableSession->servicePoint, ServicePointStatus::Paid);
            }

            return;
        }

        $metadata['last_manual_payment_at'] = now()->toISOString();
        $metadata['last_manual_payment_by_user_id'] = $recordedBy->id;

        $tableSession->fill([
            'status' => TableSessionStatus::PaymentRequested,
            'metadata' => $metadata,
        ])->save();

        if ($tableSession->servicePoint instanceof ServicePoint) {
            $this->updateServicePointStatus->handle($tableSession->servicePoint, ServicePointStatus::PaymentRequested);
        }
    }

    private function normalizeNote(?string $note): ?string
    {
        $normalized = trim((string) $note);

        return $normalized === '' ? null : mb_substr($normalized, 0, 500);
    }

    private function normalizePaymentMethod(ManualPaymentMethod|string $paymentMethod): ManualPaymentMethod
    {
        if ($paymentMethod instanceof ManualPaymentMethod) {
            return $paymentMethod;
        }

        $method = ManualPaymentMethod::tryFrom($paymentMethod);

        if (! $method instanceof ManualPaymentMethod) {
            throw ValidationException::withMessages([
                'manual_payment' => __('Выберите понятный способ оплаты.'),
            ]);
        }

        return $method;
    }

    private function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = abs($cents);
        $formatted = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }

    private function decimalToCents(string|int|float|null $amount): int
    {
        $normalized = number_format((float) ($amount ?? 0), 2, '.', '');
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = explode('.', $normalized);
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function formatPercent(string|int|float|null $percent): string
    {
        return number_format((float) ($percent ?? 0), 2, '.', '');
    }
}
