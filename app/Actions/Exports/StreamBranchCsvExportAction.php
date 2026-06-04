<?php

namespace App\Actions\Exports;

use App\Enums\DataExportType;
use App\Models\Branch;
use App\Models\ManualPayment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePoint;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamBranchCsvExportAction
{
    public function __construct(
        private readonly ResolveExportAccessibleBranchIdsAction $resolveExportAccessibleBranchIds,
    ) {}

    public function handle(User $user, Branch $branch, DataExportType $type): StreamedResponse
    {
        abort_unless($this->resolveExportAccessibleBranchIds->canExport($user, $branch), 403);

        $filename = 'restaurant-menu-'.$type->filenamePart().'-branch-'.$branch->id.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function () use ($branch, $type): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                match ($type) {
                    DataExportType::Orders => $this->writeOrders($handle, $branch),
                    DataExportType::Payments => $this->writePayments($handle, $branch),
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
    private function writeOrders(mixed $handle, Branch $branch): void
    {
        $this->putRow($handle, [
            'order_id',
            'status',
            'branch',
            'service_point',
            'table_session_id',
            'confirmed_at',
            'confirmed_by',
            'total_price',
            'currency',
            'items',
            'created_at',
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
                'total_price',
                'currency',
                'created_at',
            ])
            ->with([
                'servicePoint:id,name,display_number,internal_code',
                'confirmedByUser:id,name,email',
                'items:id,order_id,guest_name,guest_name_snapshot,item_name,item_name_snapshot,quantity,total_price',
            ])
            ->where('branch_id', $branch->id)
            ->chunkById(200, function (Collection $orders) use ($handle, $branch): void {
                $orders->each(function (Order $order) use ($handle, $branch): void {
                    $this->putRow($handle, [
                        $order->id,
                        $this->enumValue($order->status),
                        $branch->name,
                        $this->servicePointLabel($order->servicePoint),
                        $order->table_session_id,
                        $this->dateValue($order->confirmed_at),
                        $order->confirmedByUser?->name ?? '',
                        $order->total_price,
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
    private function writePayments(mixed $handle, Branch $branch): void
    {
        $this->putRow($handle, [
            'payment_id',
            'scope',
            'payment_method',
            'branch',
            'service_point',
            'table_session_id',
            'guest_name',
            'recorded_by',
            'amount',
            'currency',
            'paid_at',
            'note',
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
                'amount',
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
            ->chunkById(200, function (Collection $payments) use ($handle, $branch): void {
                $payments->each(function (ManualPayment $payment) use ($handle, $branch): void {
                    $this->putRow($handle, [
                        $payment->id,
                        $this->enumValue($payment->scope),
                        $this->enumValue($payment->payment_method),
                        $branch->name,
                        $this->servicePointLabel($payment->servicePoint),
                        $payment->table_session_id,
                        $payment->guest_name ?? '',
                        $payment->recordedBy?->name ?? '',
                        $payment->amount,
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
        $this->putRow($handle, [
            'menu_id',
            'menu_name',
            'menu_status',
            'category_id',
            'category_name',
            'parent_category',
            'item_id',
            'item_name',
            'item_description',
            'price',
            'kitchen_department',
            'weight',
            'volume',
            'calories',
            'is_available',
            'sort_order',
        ]);

        MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'kitchen_department_id',
                'name',
                'description',
                'price',
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
                        $item->menu?->name ?? '',
                        $this->enumValue($item->menu?->status),
                        $item->category_id,
                        $item->category?->name ?? '',
                        $item->category?->parent?->name ?? '',
                        $item->id,
                        $item->name,
                        $item->description ?? '',
                        $item->price,
                        $item->kitchenDepartment?->name ?? '',
                        $item->weight ?? '',
                        $item->volume ?? '',
                        $item->calories ?? '',
                        $item->is_available ? 'yes' : 'no',
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
        $this->putRow($handle, [
            'service_point_id',
            'branch',
            'area',
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
                        $servicePoint->areaNode?->name ?? '',
                        $this->enumValue($servicePoint->type),
                        $servicePoint->name,
                        $servicePoint->display_number ?? '',
                        $servicePoint->internal_code ?? '',
                        $servicePoint->capacity,
                        $this->enumValue($servicePoint->status),
                        $servicePoint->is_active ? 'yes' : 'no',
                        $servicePoint->position_x ?? '',
                        $servicePoint->position_y ?? '',
                        $this->dateValue($servicePoint->created_at),
                    ]);
                });
            });
    }

    /**
     * @param  resource  $handle
     * @param  list<mixed>  $row
     */
    private function putRow(mixed $handle, array $row): void
    {
        fputcsv($handle, $row);
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value === null ? '' : (string) $value;
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

                return $guest.$item->historicalItemName().' x'.$item->quantity.' = '.$item->total_price;
            })
            ->implode('; ');
    }
}
