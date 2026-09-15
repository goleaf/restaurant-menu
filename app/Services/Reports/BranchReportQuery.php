<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Support\MoneyFormatter;
use App\Support\Reports\BranchReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BranchReportQuery
{
    /**
     * The caller supplies branches resolved through its current report permission.
     * A period resolved for another branch set must never broaden that scope.
     *
     * @param  Collection<int, Branch>  $authorizedBranches
     * @return array{orders_count: int, order_total_cents: int|null, single_currency: string|null, default_currency: string, currency_totals: list<array{currency: string, total_cents: int, total: string, order_count: int, average_check: string|null}>, payment_currency_totals: list<array{currency: string, total_cents: int, total: string, payment_count: int}>, popular_items: list<array{item_name: string, quantity: int, total_cents: int|null, total: string}>, active_tables_count: int, closed_sessions_count: int, cancelled_orders_count: int}
     */
    public function handle(Collection $authorizedBranches, BranchReportPeriod $period): array
    {
        $branchIds = $authorizedBranches->pluck('id')->unique()->sort()->values()->all();

        if ($branchIds !== array_keys($period->ranges)) {
            throw new InvalidArgumentException('Report periods must match the authorized branches.');
        }

        $currencyTotals = $this->orderCurrencyTotals($period);
        $defaultCurrency = $authorizedBranches->first()?->currency ?: 'EUR';
        $singleCurrency = count($currencyTotals) <= 1 ? ($currencyTotals[0]['currency'] ?? $defaultCurrency) : null;

        return [
            'orders_count' => array_sum(array_column($currencyTotals, 'order_count')),
            'order_total_cents' => $singleCurrency !== null ? ($currencyTotals[0]['total_cents'] ?? 0) : null,
            'single_currency' => $singleCurrency,
            'default_currency' => $defaultCurrency,
            'currency_totals' => $currencyTotals,
            'payment_currency_totals' => $this->paymentCurrencyTotals($period),
            'popular_items' => $this->popularItems($period, $singleCurrency),
            'active_tables_count' => TableSession::query()
                ->whereIn('branch_id', $branchIds)
                ->whereIn('status', [
                    TableSessionStatus::Pending->value,
                    TableSessionStatus::Active->value,
                    TableSessionStatus::WaitingWaiterConfirmation->value,
                    TableSessionStatus::PaymentRequested->value,
                ])->count(),
            'closed_sessions_count' => $period->apply(TableSession::query(), 'ended_at')
                ->where('status', TableSessionStatus::Closed->value)->count(),
            'cancelled_orders_count' => $period->apply(Order::query(), 'updated_at')
                ->where('status', OrderStatus::Cancelled->value)->count(),
        ];
    }

    /**
     * @return list<array{currency: string, total_cents: int, total: string, order_count: int, average_check: string|null}>
     */
    private function orderCurrencyTotals(BranchReportPeriod $period): array
    {
        $matchingOrders = static fn (Builder $query): Builder => $query->scopes(['forReportPeriod' => [$period]]);

        $groups = Order::query()
            ->select('currency')
            ->forReportPeriod($period)
            ->groupBy('currency')
            ->withSum(['reportCurrencyOrders as report_total' => $matchingOrders], 'total_price_cents')
            ->withCount(['reportCurrencyOrders as report_count' => $matchingOrders])
            ->orderBy('currency')
            ->get();
        $currencies = [];

        foreach ($groups as $group) {
            $currency = $group->currency ?: 'EUR';
            $currencies[$currency] ??= ['currency' => $currency, 'total_cents' => 0, 'order_count' => 0];
            $currencies[$currency]['total_cents'] += (int) $group->getAttribute('report_total');
            $currencies[$currency]['order_count'] += (int) $group->getAttribute('report_count');
        }

        return collect($currencies)->sortKeys()->map(fn (array $currency): array => [
            'currency' => $currency['currency'],
            'total_cents' => $currency['total_cents'],
            'total' => MoneyFormatter::formatCents($currency['total_cents'], $currency['currency']),
            'order_count' => $currency['order_count'],
            'average_check' => $currency['order_count'] > 0
                ? MoneyFormatter::formatCents(MoneyFormatter::roundedDivide($currency['total_cents'], $currency['order_count']), $currency['currency'])
                : null,
        ])->values()->all();
    }

    /**
     * @return list<array{currency: string, total_cents: int, total: string, payment_count: int}>
     */
    private function paymentCurrencyTotals(BranchReportPeriod $period): array
    {
        $matchingPayments = static fn (Builder $query): Builder => $query->scopes(['forReportPeriod' => [$period]]);

        return ManualPayment::query()
            ->select('currency')
            ->forReportPeriod($period)
            ->groupBy('currency')
            ->withSum(['reportCurrencyPayments as report_total' => $matchingPayments], 'amount_cents')
            ->withCount(['reportCurrencyPayments as report_count' => $matchingPayments])
            ->orderBy('currency')
            ->get()
            ->map(fn (ManualPayment $payment): array => [
                'currency' => $payment->currency,
                'total_cents' => (int) $payment->getAttribute('report_total'),
                'total' => MoneyFormatter::formatCents((int) $payment->getAttribute('report_total'), $payment->currency),
                'payment_count' => (int) $payment->getAttribute('report_count'),
            ])->all();
    }

    /**
     * @return list<array{item_name: string, quantity: int, total_cents: int|null, total: string}>
     */
    private function popularItems(BranchReportPeriod $period, ?string $singleCurrency): array
    {
        $orders = Order::query()->select('id')->forReportPeriod($period);
        $matchingItems = function (Builder $query) use ($orders): void {
            $query->whereIn('order_id', $orders)->scopes('active')
                ->where(function (Builder $query): void {
                    $query->whereColumn('order_items.item_name_snapshot', 'report_items.item_name_snapshot')
                        ->orWhere(fn (Builder $query): Builder => $query
                            ->whereNull('order_items.item_name_snapshot')
                            ->whereNull('report_items.item_name_snapshot'));
                });
        };
        $groups = (new OrderItem)->setTable('report_items')->newQuery()
            ->from('order_items as report_items')
            ->select(['report_items.item_name', 'report_items.item_name_snapshot'])
            ->whereIn('report_items.order_id', $orders)
            ->whereNull('report_items.cancelled_at')
            ->groupBy('report_items.item_name', 'report_items.item_name_snapshot')
            ->withSum(['reportNameItems as quantity' => $matchingItems], 'quantity')
            ->withMin(['reportNameItems as first_id' => $matchingItems], 'id');

        if ($singleCurrency !== null) {
            $groups->withSum(['reportNameItems as total_price_cents' => $matchingItems], 'total_price_cents');
        }

        $items = [];

        foreach ($groups->orderBy('report_items.item_name')->orderBy('first_id')->cursor() as $group) {
            $name = $group->historicalItemName();
            $key = mb_strtolower($name);
            $items[$key] ??= ['item_name' => $name, 'quantity' => 0, 'total_cents' => $singleCurrency !== null ? 0 : null];
            $items[$key]['quantity'] += (int) $group->quantity;

            if ($singleCurrency !== null) {
                $items[$key]['total_cents'] += (int) $group->total_price_cents;
            }
        }

        return collect($items)->sortByDesc('quantity')->take(5)
            ->map(fn (array $item): array => [
                ...$item,
                'total' => $singleCurrency !== null
                    ? MoneyFormatter::formatCents((int) $item['total_cents'], $singleCurrency)
                    : __('ui.actions.analytics.buildbasicanalyticsdashboardaction.mixed'),
            ])->values()->all();
    }
}
