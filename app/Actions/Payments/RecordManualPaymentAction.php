<?php

namespace App\Actions\Payments;

use App\Actions\ServicePoints\UpdateServicePointStatusAction;
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
    ) {}

    public function recordTable(
        TableSession $tableSession,
        User $recordedBy,
        ManualPaymentMethod|string $paymentMethod,
        ?string $note = null,
    ): ManualPayment {
        return $this->record(
            tableSession: $tableSession,
            recordedBy: $recordedBy,
            paymentMethod: $paymentMethod,
            scope: ManualPaymentScope::Table,
            guest: null,
            note: $note,
        );
    }

    public function recordGuest(
        TableSession $tableSession,
        TableSessionGuest $guest,
        User $recordedBy,
        ManualPaymentMethod|string $paymentMethod,
        ?string $note = null,
    ): ManualPayment {
        return $this->record(
            tableSession: $tableSession,
            recordedBy: $recordedBy,
            paymentMethod: $paymentMethod,
            scope: ManualPaymentScope::Guest,
            guest: $guest,
            note: $note,
        );
    }

    private function record(
        TableSession $tableSession,
        User $recordedBy,
        ManualPaymentMethod|string $paymentMethod,
        ManualPaymentScope $scope,
        ?TableSessionGuest $guest,
        ?string $note,
    ): ManualPayment {
        return DB::transaction(function () use ($tableSession, $recordedBy, $paymentMethod, $scope, $guest, $note): ManualPayment {
            $tableSession = $this->reloadTableSession($tableSession);
            $method = $this->normalizePaymentMethod($paymentMethod);

            $this->ensureCanRecord($tableSession, $recordedBy);

            $summary = $this->buildPaymentSummary->handle($tableSession);
            $amountCents = $this->paymentAmountCents($summary, $scope, $guest);

            $manualPayment = ManualPayment::query()->create([
                'branch_id' => $tableSession->branch_id,
                'service_point_id' => $tableSession->service_point_id,
                'table_session_id' => $tableSession->id,
                'table_session_guest_id' => $guest?->id,
                'recorded_by_user_id' => $recordedBy->id,
                'scope' => $scope,
                'payment_method' => $method,
                'amount' => $this->formatCents($amountCents),
                'currency' => (string) $summary['currency'],
                'guest_name' => $guest?->guest_name,
                'note' => $this->normalizeNote($note),
                'paid_at' => now(),
                'metadata' => [],
            ]);

            $this->syncSessionAndServicePointStatus(
                tableSession: $tableSession,
                recordedBy: $recordedBy,
                remainingCentsAfterPayment: max(0, (int) $summary['remaining_total_cents'] - $amountCents),
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
            ->with(['servicePoint' => fn ($query) => $query->select(['id', 'branch_id', 'status', 'is_active'])])
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
     */
    private function paymentAmountCents(array $summary, ManualPaymentScope $scope, ?TableSessionGuest $guest): int
    {
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

        $remainingTotalCents = (int) $summary['remaining_total_cents'];

        if ($scope === ManualPaymentScope::Table) {
            $amountCents = $remainingTotalCents;
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

            $amountCents = min((int) $balance['remaining_cents'], $remainingTotalCents);
        }

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'manual_payment' => __('Для этой оплаты нет остатка к оплате.'),
            ]);
        }

        return $amountCents;
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
}
