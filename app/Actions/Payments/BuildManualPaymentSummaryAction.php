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
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;

class BuildManualPaymentSummaryAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(TableSession $tableSession): array
    {
        $tableSession = $this->reloadTableSession($tableSession);
        $branch = $tableSession->branch;
        $currency = $branch->currency;
        $confirmedTotalCents = $this->confirmedOrderItemsTotalCents($tableSession->orders);
        $settings = $this->settingsPayload($this->loadedSettings($branch), $branch);
        $serviceChargeTotalCents = $settings['service_charge_enabled']
            ? $this->percentageCents($confirmedTotalCents, $settings['service_charge_percent'])
            : 0;
        $coveredSubtotalCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $this->coveredSubtotalCents($payment),
        );
        $serviceChargePaidCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $this->decimalToCents($payment->service_charge_amount),
        );
        $tipsPaidTotalCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $this->decimalToCents($payment->tips_amount),
        );
        $paidTotalCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $this->decimalToCents($payment->amount),
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
            serviceChargePercent: $settings['service_charge_percent'],
            serviceChargeEnabled: $settings['service_charge_enabled'],
        );
        $unpaidGuests = $this->unpaidGuests($guestBalances);

        return [
            'currency' => $currency,
            'payment_methods' => $this->paymentMethodPayload(),
            'service_charge_enabled' => $settings['service_charge_enabled'],
            'service_charge_percent' => $settings['service_charge_percent'],
            'service_charge_total_cents' => $serviceChargeTotalCents,
            'service_charge_paid_cents' => $serviceChargePaidCents,
            'remaining_service_charge_cents' => $remainingServiceChargeCents,
            'service_charge_total' => $this->formatCents($serviceChargeTotalCents).' '.$currency,
            'service_charge_paid' => $this->formatCents($serviceChargePaidCents).' '.$currency,
            'remaining_service_charge' => $this->formatCents($remainingServiceChargeCents).' '.$currency,
            'tips_enabled' => $settings['tips_enabled'],
            'tips_paid_total_cents' => $tipsPaidTotalCents,
            'tips_paid_total' => $this->formatCents($tipsPaidTotalCents).' '.$currency,
            'confirmed_total_cents' => $confirmedTotalCents,
            'covered_subtotal_cents' => $coveredSubtotalCents,
            'remaining_subtotal_cents' => $remainingSubtotalCents,
            'paid_total_cents' => $paidTotalCents,
            'remaining_total_cents' => $remainingTotalCents,
            'confirmed_total' => $this->formatCents($confirmedTotalCents).' '.$currency,
            'covered_subtotal' => $this->formatCents($coveredSubtotalCents).' '.$currency,
            'remaining_subtotal' => $this->formatCents($remainingSubtotalCents).' '.$currency,
            'paid_total' => $this->formatCents($paidTotalCents).' '.$currency,
            'remaining_total' => $this->formatCents($remainingTotalCents).' '.$currency,
            'has_payable_total' => $confirmedTotalCents > 0,
            'has_open_draft' => $this->hasOpenDraft($tableSession->draftOrder),
            'is_fully_paid' => $isFullyPaid,
            'guest_balances' => $guestBalances,
            'unpaid_guests' => $unpaidGuests,
            'unpaid_guests_count' => count($unpaidGuests),
            'payments' => $this->paymentRows($tableSession->manualPayments, $currency),
        ];
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
                        'service_charge_percent',
                        'tips_enabled',
                    ])]),
                'guests' => fn ($query) => $query->select(['id', 'table_session_id', 'guest_name', 'status']),
                'draftOrder' => fn ($query) => $query->select(['draft_orders.id', 'draft_orders.table_session_id', 'draft_orders.status']),
                'orders' => fn ($query) => $query
                    ->select(['id', 'table_session_id', 'status', 'total_price', 'currency'])
                    ->whereNotIn('status', [OrderStatus::Cancelled->value])
                    ->with(['items' => fn ($itemQuery) => $itemQuery->select([
                        'id',
                        'order_id',
                        'table_session_guest_id',
                        'guest_name',
                        'guest_name_snapshot',
                        'total_price',
                    ])])
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
                        'covered_subtotal_amount',
                        'service_charge_percent',
                        'service_charge_amount',
                        'tips_amount',
                        'amount',
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
                fn (OrderItem $item): int => $this->decimalToCents($item->total_price),
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
        string $serviceChargePercent,
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

                $dueByGuest[$guestId] = ($dueByGuest[$guestId] ?? 0) + $this->decimalToCents($item->total_price);
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
                $serviceChargePaidByGuest[$guestId] = ($serviceChargePaidByGuest[$guestId] ?? 0) + $this->decimalToCents($payment->service_charge_amount);
                $paidTotalByGuest[$guestId] = ($paidTotalByGuest[$guestId] ?? 0) + $this->decimalToCents($payment->amount);
            });

        return $guests
            ->map(function (TableSessionGuest $guest) use ($dueByGuest, $coveredSubtotalByGuest, $serviceChargePaidByGuest, $paidTotalByGuest, $currency, $isFullyPaid, $serviceChargePercent, $serviceChargeEnabled): array {
                $subtotalDueCents = (int) ($dueByGuest[$guest->id] ?? 0);
                $serviceChargeDueCents = $serviceChargeEnabled
                    ? $this->percentageCents($subtotalDueCents, $serviceChargePercent)
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
                    'subtotal_due' => $this->formatCents($subtotalDueCents).' '.$currency,
                    'service_charge' => $this->formatCents($serviceChargeDueCents).' '.$currency,
                    'covered_subtotal' => $this->formatCents($coveredSubtotalCents).' '.$currency,
                    'service_charge_paid' => $this->formatCents($serviceChargePaidCents).' '.$currency,
                    'remaining_subtotal' => $this->formatCents($remainingSubtotalCents).' '.$currency,
                    'remaining_service_charge' => $this->formatCents($remainingServiceChargeCents).' '.$currency,
                    'due' => $this->formatCents($dueCents).' '.$currency,
                    'paid' => $this->formatCents($paidCents).' '.$currency,
                    'remaining' => $this->formatCents($guestRemainingCents).' '.$currency,
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
                    'covered_subtotal' => $this->formatCents($this->coveredSubtotalCents($payment)).' '.($payment->currency ?: $currency),
                    'service_charge_percent' => $this->formatPercent($payment->service_charge_percent),
                    'service_charge_amount' => $this->formatCents($this->decimalToCents($payment->service_charge_amount)).' '.($payment->currency ?: $currency),
                    'tips_amount' => $this->formatCents($this->decimalToCents($payment->tips_amount)).' '.($payment->currency ?: $currency),
                    'amount' => $this->formatCents($this->decimalToCents($payment->amount)).' '.($payment->currency ?: $currency),
                    'guest_name' => $payment->table_session_guest_id === null ? $payment->guest_name : $payment->guest->guest_name,
                    'recorded_by_name' => $payment->recorded_by_user_id === null ? null : $payment->recordedBy->name,
                    'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                    'note' => $payment->note,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{service_charge_enabled: bool, service_charge_percent: string, tips_enabled: bool}
     */
    private function settingsPayload(?BranchSetting $settings, ?Branch $branch): array
    {
        $defaults = BranchSetting::defaults($branch);

        if (! $settings instanceof BranchSetting) {
            return [
                'service_charge_enabled' => (bool) $defaults['service_charge_enabled'],
                'service_charge_percent' => $this->formatPercent($defaults['service_charge_percent']),
                'tips_enabled' => (bool) $defaults['tips_enabled'],
            ];
        }

        return [
            'service_charge_enabled' => (bool) $settings->service_charge_enabled,
            'service_charge_percent' => $this->formatPercent($settings->service_charge_percent),
            'tips_enabled' => (bool) $settings->tips_enabled,
        ];
    }

    private function loadedSettings(Branch $branch): ?BranchSetting
    {
        $settings = $branch->getRelation('settings');

        return $settings instanceof BranchSetting ? $settings : null;
    }

    private function coveredSubtotalCents(ManualPayment $payment): int
    {
        $coveredSubtotalCents = $this->decimalToCents($payment->covered_subtotal_amount);
        $snapshotCents = $coveredSubtotalCents
            + $this->decimalToCents($payment->service_charge_amount)
            + $this->decimalToCents($payment->tips_amount);

        if ($snapshotCents > 0) {
            return $coveredSubtotalCents;
        }

        return $this->decimalToCents($payment->amount);
    }

    private function percentageCents(int $baseCents, string|int|float|null $percent): int
    {
        if ($baseCents <= 0) {
            return 0;
        }

        $basisPoints = (int) round(((float) ($percent ?? 0)) * 100);

        return (int) round($baseCents * $basisPoints / 10000);
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

    private function decimalToCents(string|int|float|null $amount): int
    {
        return MoneyFormatter::decimalToCents($amount);
    }

    private function formatCents(int $cents): string
    {
        return MoneyFormatter::centsToDecimal($cents);
    }

    private function formatPercent(string|int|float|null $percent): string
    {
        return number_format((float) ($percent ?? 0), 2, '.', '');
    }
}
