<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\Enums\DataExportType;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Models\Branch;
use App\Models\ManualPayment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\SecurePdfRenderer;
use App\Support\MoneyFormatter;
use Carbon\CarbonInterface;

final class BuildBranchPdfReportAction
{
    private const MAX_ROWS = 500;

    public function __construct(
        private readonly ResolveExportAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly SecurePdfRenderer $pdfRenderer,
    ) {}

    /**
     * @return array{contents: string, filename: string}
     */
    public function handle(
        User $user,
        Branch $branch,
        DataExportType $type,
        CarbonInterface $startedAt,
        CarbonInterface $endedAt,
    ): array {
        abort_unless($this->resolveAccessibleBranchIds->canExport($user, $branch), 403);

        $report = match ($type) {
            DataExportType::Orders => $this->orders($branch, $startedAt, $endedAt),
            DataExportType::Payments => $this->payments($branch, $startedAt, $endedAt),
            DataExportType::Menu => $this->menu($branch),
            DataExportType::ServicePoints => $this->servicePoints($branch),
        };

        $html = view('pdf.reports.branch-report', [
            ...$report,
            'branchName' => $branch->name,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'reportTitle' => $type->label(),
        ])->render();

        return [
            'contents' => $this->pdfRenderer->render($html, 'a4', 'landscape'),
            'filename' => 'restaurant-menu-'.$type->filenamePart().'-report-branch-'.$branch->id.'-'.now()->format('Y-m-d-His').'.pdf',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orders(Branch $branch, CarbonInterface $startedAt, CarbonInterface $endedAt): array
    {
        $query = Order::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'confirmed_at',
                'total_price_cents',
                'currency',
            ])
            ->where('branch_id', $branch->id)
            ->whereBetween('confirmed_at', [$startedAt, $endedAt]);

        $totalRecords = (clone $query)->count();
        $totalCents = (int) (clone $query)->sum('total_price_cents');
        $rows = $query
            ->with(['servicePoint:id,name,display_number'])
            ->withCount('items')
            ->latest('confirmed_at')
            ->latest('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Order $order): array => [
                (string) $order->id,
                __(sprintf('reports.statuses.orders.%s', $order->status->value)),
                $this->servicePointLabel($order->servicePoint),
                $this->dateValue($order->confirmed_at),
                __('reports.pdf.items_count', ['count' => (int) $order->items_count]),
                MoneyFormatter::formatCents($order->total_price_cents, $order->currency),
            ])
            ->all();

        return $this->documentData(
            columns: [
                __('reports.csv.order_id'),
                __('reports.filters.status'),
                __('reports.csv.service_point'),
                __('reports.csv.confirmed_at'),
                __('reports.csv.items'),
                __('reports.csv.total_price'),
            ],
            rows: $rows,
            totalRecords: $totalRecords,
            period: $this->period($startedAt, $endedAt),
            totals: [[
                'label' => __('reports.revenue.net_total'),
                'value' => MoneyFormatter::formatCents($totalCents, $branch->currency),
            ]],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payments(Branch $branch, CarbonInterface $startedAt, CarbonInterface $endedAt): array
    {
        $query = ManualPayment::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'recorded_by_user_id',
                'payment_method',
                'scope',
                'guest_name',
                'amount_cents',
                'currency',
                'paid_at',
            ])
            ->where('branch_id', $branch->id)
            ->whereBetween('paid_at', [$startedAt, $endedAt]);

        $totalRecords = (clone $query)->count();
        $totalCents = (int) (clone $query)->sum('amount_cents');
        $rows = $query
            ->with([
                'servicePoint:id,name,display_number',
                'recordedBy:id,name',
            ])
            ->latest('paid_at')
            ->latest('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (ManualPayment $payment): array => [
                (string) $payment->id,
                $this->paymentLabel($payment->payment_method),
                $this->paymentLabel($payment->scope),
                $this->servicePointLabel($payment->servicePoint),
                $payment->guest_name ?? '',
                $payment->recordedBy->name ?? '',
                $this->dateValue($payment->paid_at),
                MoneyFormatter::formatCents($payment->amount_cents, $payment->currency),
            ])
            ->all();

        return $this->documentData(
            columns: [
                __('reports.csv.payment_id'),
                __('reports.payments.method'),
                __('reports.csv.scope'),
                __('reports.csv.service_point'),
                __('reports.csv.guest_name'),
                __('reports.csv.recorded_by'),
                __('reports.csv.paid_at'),
                __('reports.payments.amount'),
            ],
            rows: $rows,
            totalRecords: $totalRecords,
            period: $this->period($startedAt, $endedAt),
            totals: [[
                'label' => __('reports.revenue.total_paid'),
                'value' => MoneyFormatter::formatCents($totalCents, $branch->currency),
            ]],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function menu(Branch $branch): array
    {
        $query = MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'kitchen_department_id',
                'name',
                'price_cents',
                'is_available',
            ])
            ->whereHas('menu', fn ($menuQuery) => $menuQuery->where('branch_id', $branch->id));

        $totalRecords = (clone $query)->count();
        $rows = $query
            ->with([
                'menu:id,branch_id,name,status',
                'category:id,name',
                'kitchenDepartment:id,name',
            ])
            ->orderBy('menu_id')
            ->orderBy('category_id')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (MenuItem $item): array => [
                $item->menu->name,
                $item->category->name,
                $item->name,
                $item->kitchenDepartment->name ?? '',
                MoneyFormatter::formatCents($item->price_cents, $branch->currency),
                $item->is_available ? __('reports.csv.yes') : __('reports.csv.no'),
            ])
            ->all();

        return $this->documentData(
            columns: [
                __('reports.csv.menu_name'),
                __('reports.csv.category_name'),
                __('reports.csv.item_name'),
                __('reports.csv.kitchen_department'),
                __('reports.csv.price'),
                __('reports.csv.is_available'),
            ],
            rows: $rows,
            totalRecords: $totalRecords,
            period: __('reports.pdf.current_snapshot'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePoints(Branch $branch): array
    {
        $query = ServicePoint::query()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'capacity',
                'status',
                'is_active',
            ])
            ->where('branch_id', $branch->id);

        $totalRecords = (clone $query)->count();
        $rows = $query
            ->with(['areaNode:id,name'])
            ->orderBy('area_node_id')
            ->orderBy('display_number')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (ServicePoint $servicePoint): array => [
                $servicePoint->areaNode->name ?? __('qr.labels.no_zone'),
                __(sprintf('reports.service_point_types.%s', $servicePoint->type->value)),
                $servicePoint->name,
                $servicePoint->display_number ?? '',
                (string) $servicePoint->capacity,
                __(sprintf('reports.statuses.service_points.%s', $servicePoint->status->value)),
                $servicePoint->is_active ? __('reports.csv.yes') : __('reports.csv.no'),
            ])
            ->all();

        return $this->documentData(
            columns: [
                __('reports.csv.area'),
                __('reports.csv.type'),
                __('reports.csv.name'),
                __('reports.csv.display_number'),
                __('reports.csv.capacity'),
                __('reports.filters.status'),
                __('reports.csv.is_available'),
            ],
            rows: $rows,
            totalRecords: $totalRecords,
            period: __('reports.pdf.current_snapshot'),
        );
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     * @param  list<array{label: string, value: string}>  $totals
     * @return array<string, mixed>
     */
    private function documentData(
        array $columns,
        array $rows,
        int $totalRecords,
        string $period,
        array $totals = [],
    ): array {
        return [
            'columns' => $columns,
            'hasRows' => $rows !== [],
            'period' => $period,
            'rows' => $rows,
            'shownRecords' => count($rows),
            'totalRecords' => $totalRecords,
            'totals' => $totals,
            'truncated' => $totalRecords > self::MAX_ROWS,
        ];
    }

    private function period(CarbonInterface $startedAt, CarbonInterface $endedAt): string
    {
        return $startedAt->format('Y-m-d').' — '.$endedAt->format('Y-m-d');
    }

    private function dateValue(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : '';
    }

    private function servicePointLabel(?ServicePoint $servicePoint): string
    {
        if (! $servicePoint instanceof ServicePoint) {
            return '';
        }

        $displayNumber = filled($servicePoint->display_number) ? ' #'.$servicePoint->display_number : '';

        return $servicePoint->name.$displayNumber;
    }

    private function paymentLabel(ManualPaymentMethod|ManualPaymentScope $value): string
    {
        return $value->label();
    }
}
