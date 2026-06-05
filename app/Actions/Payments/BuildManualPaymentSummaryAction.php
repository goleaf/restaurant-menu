<?php

namespace App\Actions\Payments;

use App\Enums\DraftOrderStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\OrderStatus;
use App\Models\DraftOrder;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Collection;

class BuildManualPaymentSummaryAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(TableSession $tableSession): array
    {
        $tableSession = $this->reloadTableSession($tableSession);
        $currency = $tableSession->branch?->currency ?? 'EUR';
        $confirmedTotalCents = $this->confirmedOrderItemsTotalCents($tableSession->orders);
        $paidTotalCents = $tableSession->manualPayments->sum(
            fn (ManualPayment $payment): int => $this->decimalToCents($payment->amount),
        );
        $remainingTotalCents = max(0, $confirmedTotalCents - $paidTotalCents);
        $isFullyPaid = $confirmedTotalCents > 0 && $remainingTotalCents === 0;
        $guestBalances = $this->guestBalances(
            guests: $tableSession->guests,
            orders: $tableSession->orders,
            payments: $tableSession->manualPayments,
            currency: $currency,
            isFullyPaid: $isFullyPaid,
        );
        $unpaidGuests = $this->unpaidGuests($guestBalances);

        return [
            'currency' => $currency,
            'payment_methods' => $this->paymentMethodPayload(),
            'confirmed_total_cents' => $confirmedTotalCents,
            'paid_total_cents' => $paidTotalCents,
            'remaining_total_cents' => $remainingTotalCents,
            'confirmed_total' => $this->formatCents($confirmedTotalCents).' '.$currency,
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
                'branch' => fn ($query) => $query->select(['id', 'currency']),
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

        $status = $draftOrder->status instanceof DraftOrderStatus
            ? $draftOrder->status
            : DraftOrderStatus::from((string) $draftOrder->status);

        return in_array($status, [
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
    ): array {
        $dueByGuest = [];
        $paidByGuest = [];

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
            ->each(function (ManualPayment $payment) use (&$paidByGuest): void {
                $guestId = (int) $payment->table_session_guest_id;

                if ($guestId < 1) {
                    return;
                }

                $paidByGuest[$guestId] = ($paidByGuest[$guestId] ?? 0) + $this->decimalToCents($payment->amount);
            });

        return $guests
            ->map(function (TableSessionGuest $guest) use ($dueByGuest, $paidByGuest, $currency, $isFullyPaid): array {
                $dueCents = (int) ($dueByGuest[$guest->id] ?? 0);
                $paidCents = (int) ($paidByGuest[$guest->id] ?? 0);
                $guestRemainingCents = max(0, $dueCents - $paidCents);
                $isCoveredByTablePayment = $isFullyPaid && $guestRemainingCents > 0;

                if ($isCoveredByTablePayment) {
                    $guestRemainingCents = 0;
                }

                return [
                    'guest_id' => $guest->id,
                    'guest_name' => $guest->guest_name,
                    'due_cents' => $dueCents,
                    'paid_cents' => $paidCents,
                    'remaining_cents' => $guestRemainingCents,
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
                $scope = $payment->scope instanceof ManualPaymentScope
                    ? $payment->scope
                    : ManualPaymentScope::from((string) $payment->scope);
                $method = $payment->payment_method instanceof ManualPaymentMethod
                    ? $payment->payment_method
                    : ManualPaymentMethod::from((string) $payment->payment_method);

                return [
                    'id' => $payment->id,
                    'scope' => $scope->value,
                    'scope_label' => $scope->label(),
                    'method' => $method->value,
                    'method_label' => $method->label(),
                    'amount' => $this->formatCents($this->decimalToCents($payment->amount)).' '.($payment->currency ?: $currency),
                    'guest_name' => $payment->guest?->guest_name ?? $payment->guest_name,
                    'recorded_by_name' => $payment->recordedBy?->name,
                    'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                    'note' => $payment->note,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function paymentMethodPayload(): array
    {
        return collect(ManualPaymentMethod::cases())
            ->map(fn (ManualPaymentMethod $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ])
            ->values()
            ->all();
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

    private function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = abs($cents);
        $formatted = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
