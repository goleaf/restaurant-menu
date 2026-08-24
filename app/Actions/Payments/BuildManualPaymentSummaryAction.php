<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\DraftOrderStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DraftOrder;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Support\LocalizedDateFormatter;
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BuildManualPaymentSummaryAction
{
    private const int NORMALIZED_FIELD_LIMIT = 50;

    /**
     * @return array<string, mixed>
     */
    public function handle(TableSession $tableSession): array
    {
        $tableSession = $this->reloadTableSession($tableSession);
        $branch = $tableSession->branch;
        $currency = $branch->currency;
        $normalization = ['count' => 0, 'fields' => []];
        $serviceChargeBasisPointsDefault = (int) BranchSetting::defaults($branch)['service_charge_basis_points'];
        $this->normalizeNullableBranchSettings($branch, $serviceChargeBasisPointsDefault, $normalization);
        $confirmedTotalCents = $this->confirmedOrderItemsTotalCents($tableSession->orders);
        $settings = $this->settingsPayload($this->loadedSettings($branch), $branch);
        $this->normalizeNullablePaymentSnapshots(
            payments: $tableSession->manualPayments,
            serviceChargeBasisPointsFallback: $serviceChargeBasisPointsDefault,
            normalization: $normalization,
        );
        $serviceChargeTotalCents = $settings['service_charge_enabled']
            ? MoneyFormatter::percentageOf($confirmedTotalCents, $settings['service_charge_basis_points'])
            : 0;
        $coveredSubtotalCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $this->coveredSubtotalCents($payment),
        );
        $serviceChargePaidCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $payment->service_charge_cents,
        );
        $tipsPaidTotalCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $payment->tips_cents,
        );
        $paidTotalCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $payment->amount_cents,
        );
        $remainingSubtotalCents = max(0, $confirmedTotalCents - $coveredSubtotalCents);
        $remainingServiceChargeCents = max(0, $serviceChargeTotalCents - $serviceChargePaidCents);
        $remainingTotalCents = $remainingSubtotalCents + $remainingServiceChargeCents;
        $isFullyPaid = $confirmedTotalCents > 0 && $remainingSubtotalCents === 0 && $remainingServiceChargeCents === 0;
        $guestBalances = $this->guestBalances(
            guests: $tableSession->guests,
            orders: $tableSession->orders,
            payments: $tableSession->manualPayments,
            currency: $currency,
            isFullyPaid: $isFullyPaid,
            serviceChargeBasisPoints: $settings['service_charge_basis_points'],
            serviceChargeEnabled: $settings['service_charge_enabled'],
        );
        $unpaidGuests = $this->unpaidGuests($guestBalances);

        $summary = [
            'currency' => $currency,
            'payment_methods' => $this->paymentMethodPayload(),
            'service_charge_enabled' => $settings['service_charge_enabled'],
            'service_charge_basis_points' => $settings['service_charge_basis_points'],
            'service_charge_percent' => MoneyFormatter::centsToDecimal($settings['service_charge_basis_points']),
            'service_charge_total_cents' => $serviceChargeTotalCents,
            'service_charge_paid_cents' => $serviceChargePaidCents,
            'remaining_service_charge_cents' => $remainingServiceChargeCents,
            'service_charge_total' => $this->formatCents($serviceChargeTotalCents, $currency),
            'service_charge_paid' => $this->formatCents($serviceChargePaidCents, $currency),
            'remaining_service_charge' => $this->formatCents($remainingServiceChargeCents, $currency),
            'tips_enabled' => $settings['tips_enabled'],
            'tips_paid_total_cents' => $tipsPaidTotalCents,
            'tips_paid_total' => $this->formatCents($tipsPaidTotalCents, $currency),
            'confirmed_total_cents' => $confirmedTotalCents,
            'covered_subtotal_cents' => $coveredSubtotalCents,
            'remaining_subtotal_cents' => $remainingSubtotalCents,
            'paid_total_cents' => $paidTotalCents,
            'remaining_total_cents' => $remainingTotalCents,
            'confirmed_total' => $this->formatCents($confirmedTotalCents, $currency),
            'covered_subtotal' => $this->formatCents($coveredSubtotalCents, $currency),
            'remaining_subtotal' => $this->formatCents($remainingSubtotalCents, $currency),
            'paid_total' => $this->formatCents($paidTotalCents, $currency),
            'remaining_total' => $this->formatCents($remainingTotalCents, $currency),
            'has_payable_total' => $confirmedTotalCents > 0,
            'has_open_draft' => $this->hasOpenDraft($tableSession->draftOrder),
            'is_fully_paid' => $isFullyPaid,
            'guest_balances' => $guestBalances,
            'unpaid_guests' => $unpaidGuests,
            'unpaid_guests_count' => count($unpaidGuests),
            'payments' => $this->paymentRows($tableSession->manualPayments, $currency),
        ];

        $this->warnAboutNormalizedSnapshots($tableSession, $normalization);

        return $summary;
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'status'])
            ->with([
                'branch' => fn ($query) => $query
                    ->select(['id', 'currency'])
                    ->with(['settings' => fn ($settingsQuery) => $settingsQuery->select([
                        'id',
                        'branch_id',
                        'service_charge_enabled',
                        'service_charge_basis_points',
                        'tips_enabled',
                    ])]),
                'guests' => fn ($query) => $query->select(['id', 'table_session_id', 'guest_name', 'status']),
                'draftOrder' => fn ($query) => $query->select(['draft_orders.id', 'draft_orders.table_session_id', 'draft_orders.status']),
                'orders' => fn ($query) => $query
                    ->select(['id', 'table_session_id', 'status', 'total_price_cents', 'currency'])
                    ->whereNotIn('status', [OrderStatus::Cancelled->value])
                    ->with(['items' => fn ($itemQuery) => $itemQuery
                        ->select([
                            'id',
                            'order_id',
                            'table_session_guest_id',
                            'guest_name',
                            'guest_name_snapshot',
                            'total_price_cents',
                        ])
                        ->active()])
                    ->orderBy('created_at')
                    ->orderBy('id'),
                'manualPayments' => fn ($query) => $query
                    ->select([
                        'id',
                        'table_session_id',
                        'table_session_guest_id',
                        'recorded_by_user_id',
                        'scope',
                        'payment_method',
                        'covered_subtotal_cents',
                        'service_charge_basis_points',
                        'service_charge_cents',
                        'tips_cents',
                        'amount_cents',
                        'currency',
                        'guest_name',
                        'note',
                        'paid_at',
                    ])
                    ->with([
                        'guest' => fn ($guestQuery) => $guestQuery->select(['id', 'table_session_id', 'guest_name']),
                        'recordedBy' => fn ($userQuery) => $userQuery->select(['id', 'name']),
                    ]),
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function confirmedOrderItemsTotalCents(Collection $orders): int
    {
        return $orders->sum(
            fn (Order $order): int => $order->items->sum(
                fn (OrderItem $item): int => $item->total_price_cents,
            ),
        );
    }

    private function hasOpenDraft(?DraftOrder $draftOrder): bool
    {
        if (! $draftOrder instanceof DraftOrder) {
            return false;
        }

        return in_array($draftOrder->status, [
            DraftOrderStatus::Draft,
            DraftOrderStatus::SentToWaiter,
            DraftOrderStatus::WaiterReview,
        ], true);
    }

    /**
     * @param  Collection<int, TableSessionGuest>  $guests
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, ManualPayment>  $payments
     * @return list<array<string, mixed>>
     */
    private function guestBalances(
        Collection $guests,
        Collection $orders,
        Collection $payments,
        string $currency,
        bool $isFullyPaid,
        int $serviceChargeBasisPoints,
        bool $serviceChargeEnabled,
    ): array {
        $dueByGuest = [];
        $coveredSubtotalByGuest = [];
        $serviceChargePaidByGuest = [];
        $paidTotalByGuest = [];

        $orders->each(function (Order $order) use (&$dueByGuest): void {
            $order->items->each(function (OrderItem $item) use (&$dueByGuest): void {
                $guestId = (int) $item->table_session_guest_id;

                if ($guestId < 1) {
                    return;
                }

                $dueByGuest[$guestId] = ($dueByGuest[$guestId] ?? 0) + $item->total_price_cents;
            });
        });

        $payments
            ->filter(fn (ManualPayment $payment): bool => $payment->scope === ManualPaymentScope::Guest)
            ->each(function (ManualPayment $payment) use (&$coveredSubtotalByGuest, &$serviceChargePaidByGuest, &$paidTotalByGuest): void {
                $guestId = (int) $payment->table_session_guest_id;

                if ($guestId < 1) {
                    return;
                }

                $coveredSubtotalByGuest[$guestId] = ($coveredSubtotalByGuest[$guestId] ?? 0) + $this->coveredSubtotalCents($payment);
                $serviceChargePaidByGuest[$guestId] = ($serviceChargePaidByGuest[$guestId] ?? 0) + $payment->service_charge_cents;
                $paidTotalByGuest[$guestId] = ($paidTotalByGuest[$guestId] ?? 0) + $payment->amount_cents;
            });

        return $guests
            ->map(function (TableSessionGuest $guest) use ($dueByGuest, $coveredSubtotalByGuest, $serviceChargePaidByGuest, $paidTotalByGuest, $currency, $isFullyPaid, $serviceChargeBasisPoints, $serviceChargeEnabled): array {
                $subtotalDueCents = (int) ($dueByGuest[$guest->id] ?? 0);
                $serviceChargeDueCents = $serviceChargeEnabled
                    ? MoneyFormatter::percentageOf($subtotalDueCents, $serviceChargeBasisPoints)
                    : 0;
                $dueCents = $subtotalDueCents + $serviceChargeDueCents;
                $coveredSubtotalCents = (int) ($coveredSubtotalByGuest[$guest->id] ?? 0);
                $serviceChargePaidCents = (int) ($serviceChargePaidByGuest[$guest->id] ?? 0);
                $paidCents = (int) ($paidTotalByGuest[$guest->id] ?? 0);
                $remainingSubtotalCents = max(0, $subtotalDueCents - $coveredSubtotalCents);
                $remainingServiceChargeCents = max(0, $serviceChargeDueCents - $serviceChargePaidCents);
                $guestRemainingCents = $remainingSubtotalCents + $remainingServiceChargeCents;
                $isCoveredByTablePayment = $isFullyPaid && $guestRemainingCents > 0;

                if ($isCoveredByTablePayment) {
                    $remainingSubtotalCents = 0;
                    $remainingServiceChargeCents = 0;
                    $guestRemainingCents = 0;
                }

                return [
                    'guest_id' => $guest->id,
                    'guest_name' => $guest->guest_name,
                    'subtotal_due_cents' => $subtotalDueCents,
                    'service_charge_cents' => $serviceChargeDueCents,
                    'covered_subtotal_cents' => $coveredSubtotalCents,
                    'service_charge_paid_cents' => $serviceChargePaidCents,
                    'remaining_subtotal_cents' => $remainingSubtotalCents,
                    'remaining_service_charge_cents' => $remainingServiceChargeCents,
                    'due_cents' => $dueCents,
                    'paid_cents' => $paidCents,
                    'remaining_cents' => $guestRemainingCents,
                    'subtotal_due' => $this->formatCents($subtotalDueCents, $currency),
                    'service_charge' => $this->formatCents($serviceChargeDueCents, $currency),
                    'covered_subtotal' => $this->formatCents($coveredSubtotalCents, $currency),
                    'service_charge_paid' => $this->formatCents($serviceChargePaidCents, $currency),
                    'remaining_subtotal' => $this->formatCents($remainingSubtotalCents, $currency),
                    'remaining_service_charge' => $this->formatCents($remainingServiceChargeCents, $currency),
                    'due' => $this->formatCents($dueCents, $currency),
                    'paid' => $this->formatCents($paidCents, $currency),
                    'remaining' => $this->formatCents($guestRemainingCents, $currency),
                    'is_paid' => $dueCents > 0 && $guestRemainingCents === 0,
                    'covered_by_table_payment' => $isCoveredByTablePayment,
                ];
            })
            ->sortBy(fn (array $guestBalance): string => mb_strtolower($guestBalance['guest_name']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $guestBalances
     * @return list<array<string, mixed>>
     */
    private function unpaidGuests(array $guestBalances): array
    {
        return collect($guestBalances)
            ->filter(fn (array $guestBalance): bool => (int) ($guestBalance['remaining_cents'] ?? 0) > 0)
            ->map(fn (array $guestBalance): array => [
                'guest_id' => $guestBalance['guest_id'],
                'guest_name' => $guestBalance['guest_name'],
                'remaining_cents' => $guestBalance['remaining_cents'],
                'remaining' => $guestBalance['remaining'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ManualPayment>  $payments
     * @return list<array<string, mixed>>
     */
    private function paymentRows(Collection $payments, string $currency): array
    {
        return $payments
            ->sortByDesc(fn (ManualPayment $payment): int => $payment->paid_at?->getTimestamp() ?? 0)
            ->map(function (ManualPayment $payment) use ($currency): array {
                $scope = $payment->scope;
                $method = $payment->payment_method;

                return [
                    'id' => $payment->id,
                    'scope' => $scope->value,
                    'scope_label' => $scope->label(),
                    'method' => $method->value,
                    'method_label' => $method->translationKey(),
                    'covered_subtotal' => $this->formatCents($this->coveredSubtotalCents($payment), $payment->currency ?: $currency),
                    'service_charge_percent' => MoneyFormatter::centsToDecimal($payment->service_charge_basis_points),
                    'service_charge_amount' => $this->formatCents($payment->service_charge_cents, $payment->currency ?: $currency),
                    'tips_amount' => $this->formatCents($payment->tips_cents, $payment->currency ?: $currency),
                    'amount' => $this->formatCents($payment->amount_cents, $payment->currency ?: $currency),
                    'guest_name' => $payment->table_session_guest_id === null ? $payment->guest_name : $payment->guest->guest_name,
                    'recorded_by_name' => $payment->recorded_by_user_id === null ? null : $payment->recordedBy->name,
                    'paid_at' => LocalizedDateFormatter::dateTime($payment->paid_at),
                    'note' => $payment->note,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{service_charge_enabled: bool, service_charge_basis_points: int, tips_enabled: bool}
     */
    private function settingsPayload(?BranchSetting $settings, ?Branch $branch): array
    {
        $defaults = BranchSetting::defaults($branch);

        if (! $settings instanceof BranchSetting) {
            return [
                'service_charge_enabled' => (bool) $defaults['service_charge_enabled'],
                'service_charge_basis_points' => (int) $defaults['service_charge_basis_points'],
                'tips_enabled' => (bool) $defaults['tips_enabled'],
            ];
        }

        return [
            'service_charge_enabled' => (bool) $settings->service_charge_enabled,
            'service_charge_basis_points' => $settings->service_charge_basis_points,
            'tips_enabled' => (bool) $settings->tips_enabled,
        ];
    }

    private function loadedSettings(Branch $branch): ?BranchSetting
    {
        $settings = $branch->getRelation('settings');

        return $settings instanceof BranchSetting ? $settings : null;
    }

    /**
     * @param  array{count: int, fields: list<array{record_type: string, record_id: int, column: string}>}  $normalization
     */
    private function normalizeNullableBranchSettings(
        Branch $branch,
        int $serviceChargeBasisPointsDefault,
        array &$normalization,
    ): void {
        $settings = $this->loadedSettings($branch);

        if (! $settings instanceof BranchSetting || $settings->getAttribute('service_charge_basis_points') !== null) {
            return;
        }

        $settings->setAttribute('service_charge_basis_points', $serviceChargeBasisPointsDefault);
        $this->recordNormalizedField(
            normalization: $normalization,
            recordType: 'branch_setting',
            recordId: $settings->id,
            column: 'service_charge_basis_points',
        );
    }

    /**
     * @param  Collection<int, ManualPayment>  $payments
     * @param  array{count: int, fields: list<array{record_type: string, record_id: int, column: string}>}  $normalization
     */
    private function normalizeNullablePaymentSnapshots(
        Collection $payments,
        int $serviceChargeBasisPointsFallback,
        array &$normalization,
    ): void {
        $fallbacks = [
            'covered_subtotal_cents' => 0,
            'service_charge_basis_points' => $serviceChargeBasisPointsFallback,
            'service_charge_cents' => 0,
            'tips_cents' => 0,
            'amount_cents' => 0,
        ];

        $payments->each(function (ManualPayment $payment) use ($fallbacks, &$normalization): void {
            foreach ($fallbacks as $column => $fallback) {
                if ($payment->getAttribute($column) !== null) {
                    continue;
                }

                $payment->setAttribute($column, $fallback);
                $this->recordNormalizedField(
                    normalization: $normalization,
                    recordType: 'manual_payment',
                    recordId: $payment->id,
                    column: $column,
                );
            }
        });
    }

    /**
     * @param  array{count: int, fields: list<array{record_type: string, record_id: int, column: string}>}  $normalization
     */
    private function recordNormalizedField(
        array &$normalization,
        string $recordType,
        int $recordId,
        string $column,
    ): void {
        $normalization['count']++;

        if (count($normalization['fields']) >= self::NORMALIZED_FIELD_LIMIT) {
            return;
        }

        $normalization['fields'][] = [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'column' => $column,
        ];
    }

    /**
     * @param  array{count: int, fields: list<array{record_type: string, record_id: int, column: string}>}  $normalization
     */
    private function warnAboutNormalizedSnapshots(TableSession $tableSession, array $normalization): void
    {
        if ($normalization['count'] === 0) {
            return;
        }

        Log::warning('manual_payment_summary_nullable_snapshots_normalized', [
            'event' => 'manual_payment_summary_nullable_snapshots_normalized',
            'table_session_id' => $tableSession->id,
            'normalized_count' => $normalization['count'],
            'normalized_fields' => $normalization['fields'],
            'normalized_fields_truncated' => $normalization['count'] > count($normalization['fields']),
        ]);
    }

    private function coveredSubtotalCents(ManualPayment $payment): int
    {
        return $payment->covered_subtotal_cents;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function paymentMethodPayload(): array
    {
        return collect(ManualPaymentMethod::cases())
            ->map(fn (ManualPaymentMethod $method): array => [
                'value' => $method->value,
                'label' => $method->translationKey(),
            ])
            ->values()
            ->all();
    }

    private function formatCents(int $cents, string $currency): string
    {
        return MoneyFormatter::formatCents($cents, $currency);
    }
}
