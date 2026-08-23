<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\Enums\DataExportType;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\Branch;
use App\Models\ManualPayment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\MoneyFormatter;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamBranchCsvExportAction
{
    public function __construct(
        private readonly ResolveExportAccessibleBranchIdsAction $resolveExportAccessibleBranchIds,
    ) {}

    public function handle(
        User $user,
        Branch $branch,
        DataExportType $type,
        ?CarbonInterface $startedAt = null,
        ?CarbonInterface $endedAt = null,
    ): StreamedResponse {
        abort_unless($this->resolveExportAccessibleBranchIds->canExport($user, $branch), 403);

        $filename = 'restaurant-menu-'.$type->filenamePart().'-branch-'.$branch->id.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function () use ($branch, $type, $startedAt, $endedAt): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                match ($type) {
                    DataExportType::Orders => $this->writeOrders($handle, $branch, $startedAt, $endedAt),
                    DataExportType::Payments => $this->writePayments($handle, $branch, $startedAt, $endedAt),
                    DataExportType::Menu => $this->writeMenu($handle, $branch),
                    DataExportType::ServicePoints => $this->writeServicePoints($handle, $branch),
                };

                fclose($handle);
            },
            $filename,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * @param  resource  $handle
     */
    private function writeOrders(mixed $handle, Branch $branch, ?CarbonInterface $startedAt, ?CarbonInterface $endedAt): void
    {
        $this->putHeader($handle, [
            'reports.csv.order_id',
            'reports.filters.status',
            'reports.csv.branch',
            'reports.csv.service_point',
            'reports.csv.table_session_id',
            'reports.csv.confirmed_at',
            'reports.csv.confirmed_by',
            'reports.csv.total_price',
            'reports.csv.currency',
            'reports.csv.items',
            'reports.csv.created_at',
        ]);

        Order::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'table_session_id',
                'status',
                'confirmed_by_user_id',
                'confirmed_at',
                'total_price_cents',
                'currency',
                'created_at',
            ])
            ->with([
                'servicePoint:id,name,display_number,internal_code',
                'confirmedByUser:id,name,email',
                'items:id,order_id,guest_name,guest_name_snapshot,item_name,item_name_snapshot,quantity,total_price_cents,cancelled_at,cancellation_reason',
            ])
            ->where('branch_id', $branch->id)
            ->when($startedAt instanceof CarbonInterface && $endedAt instanceof CarbonInterface, function ($query) use ($startedAt, $endedAt): void {
                $query->whereBetween('confirmed_at', [$startedAt, $endedAt]);
            })
            ->chunkById(200, function (Collection $orders) use ($handle, $branch): void {
                $orders->each(function (Order $order) use ($handle, $branch): void {
                    $this->putRow($handle, [
                        $order->id,
                        $this->enumLabel($order->status),
                        $branch->name,
                        $this->servicePointLabel($order->servicePoint),
                        $order->table_session_id,
                        $this->dateValue($order->confirmed_at),
                        $order->confirmed_by_user_id === null ? '' : $order->confirmedByUser->name,
                        MoneyFormatter::centsToDecimal($order->total_price_cents),
                        $order->currency,
                        $this->orderItemsSummary($order->items),
                        $this->dateValue($order->created_at),
                    ]);
                });
            });
    }

    /**
     * @param  resource  $handle
     */
    private function writePayments(mixed $handle, Branch $branch, ?CarbonInterface $startedAt, ?CarbonInterface $endedAt): void
    {
        $this->putHeader($handle, [
            'reports.csv.payment_id',
            'reports.csv.scope',
            'reports.payments.method',
            'reports.csv.branch',
            'reports.csv.service_point',
            'reports.csv.table_session_id',
            'reports.csv.guest_name',
            'reports.csv.recorded_by',
            'reports.payments.amount',
            'reports.csv.currency',
            'reports.csv.paid_at',
            'reports.csv.note',
        ]);

        ManualPayment::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'table_session_id',
                'recorded_by_user_id',
                'scope',
                'payment_method',
                'amount_cents',
                'currency',
                'guest_name',
                'note',
                'paid_at',
            ])
            ->with([
                'servicePoint:id,name,display_number,internal_code',
                'recordedBy:id,name,email',
            ])
            ->where('branch_id', $branch->id)
            ->when($startedAt instanceof CarbonInterface && $endedAt instanceof CarbonInterface, function ($query) use ($startedAt, $endedAt): void {
                $query->whereBetween('paid_at', [$startedAt, $endedAt]);
            })
            ->chunkById(200, function (Collection $payments) use ($handle, $branch): void {
                $payments->each(function (ManualPayment $payment) use ($handle, $branch): void {
                    $this->putRow($handle, [
                        $payment->id,
                        $this->enumLabel($payment->scope),
                        $this->enumLabel($payment->payment_method),
                        $branch->name,
                        $this->servicePointLabel($payment->servicePoint),
                        $payment->table_session_id,
                        $payment->guest_name ?? '',
                        $payment->recorded_by_user_id === null ? '' : $payment->recordedBy->name,
                        MoneyFormatter::centsToDecimal($payment->amount_cents),
                        $payment->currency,
                        $this->dateValue($payment->paid_at),
                        $payment->note ?? '',
                    ]);
                });
            });
    }

    /**
     * @param  resource  $handle
     */
    private function writeMenu(mixed $handle, Branch $branch): void
    {
        $this->putHeader($handle, [
            'reports.csv.menu_id',
            'reports.csv.menu_name',
            'reports.csv.menu_status',
            'reports.csv.category_id',
            'reports.csv.category_name',
            'reports.csv.parent_category',
            'reports.csv.item_id',
            'reports.csv.item_name',
            'reports.csv.item_description',
            'reports.csv.price',
            'reports.csv.kitchen_department',
            'reports.csv.weight',
            'reports.csv.volume',
            'reports.csv.calories',
            'reports.csv.is_available',
            'reports.csv.sort_order',
        ]);

        MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'kitchen_department_id',
                'name',
                'description',
                'price_cents',
                'weight',
                'volume',
                'calories',
                'is_available',
                'sort_order',
            ])
            ->with([
                'menu:id,branch_id,name,status',
                'category:id,parent_id,name',
                'category.parent:id,name',
                'kitchenDepartment:id,name,type',
            ])
            ->whereHas('menu', function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id);
            })
            ->chunkById(200, function (Collection $items) use ($handle): void {
                $items->each(function (MenuItem $item) use ($handle): void {
                    $this->putRow($handle, [
                        $item->menu_id,
                        $item->menu->name,
                        $this->enumLabel($item->menu->status),
                        $item->category_id,
                        $item->category->name,
                        $item->category->parent_id === null ? '' : $item->category->parent->name,
                        $item->id,
                        $item->name,
                        $item->description ?? '',
                        MoneyFormatter::centsToDecimal($item->price_cents),
                        $item->kitchen_department_id === null ? '' : $item->kitchenDepartment->name,
                        $item->weight ?? '',
                        $item->volume ?? '',
                        $item->calories ?? '',
                        $this->booleanLabel((bool) $item->is_available),
                        $item->sort_order,
                    ]);
                });
            });
    }

    /**
     * @param  resource  $handle
     */
    private function writeServicePoints(mixed $handle, Branch $branch): void
    {
        $this->putHeader($handle, [
            'reports.csv.service_point_id',
            'reports.csv.branch',
            'reports.csv.area',
            'reports.csv.type',
            'reports.csv.name',
            'reports.csv.display_number',
            'reports.csv.internal_code',
            'reports.csv.capacity',
            'reports.filters.status',
            'reports.csv.is_available',
            'reports.csv.position_x',
            'reports.csv.position_y',
            'reports.csv.created_at',
        ]);

        ServicePoint::query()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'internal_code',
                'capacity',
                'status',
                'is_active',
                'position_x',
                'position_y',
                'created_at',
            ])
            ->with(['areaNode:id,name'])
            ->where('branch_id', $branch->id)
            ->chunkById(200, function (Collection $servicePoints) use ($handle, $branch): void {
                $servicePoints->each(function (ServicePoint $servicePoint) use ($handle, $branch): void {
                    $this->putRow($handle, [
                        $servicePoint->id,
                        $branch->name,
                        $servicePoint->area_node_id === null ? '' : $servicePoint->areaNode->name,
                        $this->enumLabel($servicePoint->type),
                        $servicePoint->name,
                        $servicePoint->display_number ?? '',
                        $servicePoint->internal_code ?? '',
                        $servicePoint->capacity,
                        $this->enumLabel($servicePoint->status),
                        $this->booleanLabel((bool) $servicePoint->is_active),
                        $servicePoint->position_x ?? '',
                        $servicePoint->position_y ?? '',
                        $this->dateValue($servicePoint->created_at),
                    ]);
                });
            });
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $keys
     */
    private function putHeader(mixed $handle, array $keys): void
    {
        $this->putRow(
            $handle,
            array_map(fn (string $key): string => __($key), $keys),
        );
    }

    /**
     * @param  resource  $handle
     * @param  list<mixed>  $row
     */
    private function putRow(mixed $handle, array $row): void
    {
        fputcsv($handle, $row);
    }

    private function enumLabel(mixed $value): string
    {
        if ($value instanceof OrderStatus) {
            return __(sprintf('reports.statuses.orders.%s', $value->value));
        }

        if ($value instanceof MenuStatus) {
            return __(sprintf('reports.statuses.menu.%s', $value->value));
        }

        if ($value instanceof ServicePointStatus) {
            return __(sprintf('reports.statuses.service_points.%s', $value->value));
        }

        if ($value instanceof ServicePointType) {
            return __(sprintf('reports.service_point_types.%s', $value->value));
        }

        if ($value instanceof ManualPaymentMethod || $value instanceof ManualPaymentScope) {
            return $value->label();
        }

        if ($value instanceof BackedEnum) {
            return method_exists($value, 'label') ? __($value->label()) : (string) $value->value;
        }

        return $value === null ? '' : (string) $value;
    }

    private function booleanLabel(bool $value): string
    {
        return $value ? __('reports.csv.yes') : __('reports.csv.no');
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

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private function orderItemsSummary(Collection $items): string
    {
        return $items
            ->map(function (OrderItem $item): string {
                $guestName = $item->historicalGuestName();
                $guest = filled($guestName) ? $guestName.': ' : '';
                $cancellation = $item->isCancelled()
                    ? ' ['.__('reports.csv.cancelled_order_item', [
                        'reason' => $item->cancellation_reason,
                    ]).']'
                    : '';

                return $guest.$item->historicalItemName().' x'.$item->quantity.' = '.MoneyFormatter::centsToDecimal($item->total_price_cents).$cancellation;
            })
            ->implode('; ');
    }
}
