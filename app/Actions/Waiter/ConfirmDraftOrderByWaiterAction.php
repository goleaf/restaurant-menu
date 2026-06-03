<?php

namespace App\Actions\Waiter;

use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmDraftOrderByWaiterAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    public function handle(DraftOrder $draftOrder, User $confirmedBy): Order
    {
        return DB::transaction(function () use ($draftOrder, $confirmedBy): Order {
            $draftOrder = $this->reloadDraftOrder($draftOrder);

            $this->ensureCanConfirm($draftOrder, $confirmedBy);

            if ($draftOrder->status === DraftOrderStatus::ConvertedToOrder && $draftOrder->order instanceof Order) {
                return $draftOrder->order;
            }

            $previousStatus = $draftOrder->status;
            $currency = $draftOrder->tableSession?->branch?->currency ?? 'EUR';
            $totalCents = $draftOrder->items->sum(
                fn (DraftOrderItem $item): int => $this->decimalToCents($item->total_price),
            );

            $order = Order::query()->create([
                'branch_id' => $draftOrder->tableSession->branch_id,
                'service_point_id' => $draftOrder->tableSession->service_point_id,
                'table_session_id' => $draftOrder->table_session_id,
                'draft_order_id' => $draftOrder->id,
                'status' => OrderStatus::ConfirmedByWaiter,
                'confirmed_by_user_id' => $confirmedBy->id,
                'confirmed_at' => now(),
                'total_price' => $this->formatCents($totalCents),
                'currency' => $currency,
                'metadata' => [
                    'source' => 'draft_order',
                    'kitchen_dispatch_prepared' => true,
                    'sent_to_kitchen' => false,
                    'sent_to_bar' => false,
                ],
            ]);

            $draftOrder->items->each(function (DraftOrderItem $item) use ($order): void {
                $kitchenDepartment = $item->menuItem?->kitchenDepartment;

                $order->items()->create([
                    'table_session_guest_id' => $item->table_session_guest_id,
                    'menu_item_id' => $item->menu_item_id,
                    'kitchen_department_id' => $kitchenDepartment?->id,
                    'kitchen_department_type' => $kitchenDepartment?->type?->value,
                    'kitchen_department_name' => $kitchenDepartment?->name,
                    'guest_name' => $item->guest?->guest_name,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'modifier_total' => $item->modifier_total,
                    'total_price' => $item->total_price,
                    'selected_modifiers' => $item->selected_modifiers ?? [],
                    'comment' => $item->comment,
                ]);
            });

            $draftOrder
                ->forceFill([
                    'status' => DraftOrderStatus::ConvertedToOrder,
                    'converted_to_order_at' => now(),
                    'converted_by_user_id' => $confirmedBy->id,
                    'rejected_at' => null,
                    'rejected_by_user_id' => null,
                    'rejection_reason' => null,
                ])
                ->save();

            if ($draftOrder->tableSession?->servicePoint !== null) {
                $this->updateServicePointStatus->handle($draftOrder->tableSession->servicePoint, ServicePointStatus::Occupied);
            }

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftConfirmed,
                order: $order,
                draftOrder: $draftOrder,
                actorUser: $confirmedBy,
                previousStatus: $previousStatus,
                newStatus: DraftOrderStatus::ConvertedToOrder,
                statusType: 'draft_order',
                metadata: [
                    'order_status' => OrderStatus::ConfirmedByWaiter->value,
                    'items_count' => $draftOrder->items->count(),
                ],
            );

            return $order->refresh();
        });
    }

    private function reloadDraftOrder(DraftOrder $draftOrder): DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
                'sent_to_waiter_at',
                'sent_by_guest_id',
                'rejected_at',
                'rejected_by_user_id',
                'rejection_reason',
                'converted_to_order_at',
                'converted_by_user_id',
            ])
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'service_point_id', 'status'])
                    ->with([
                        'branch:id,organization_id,currency',
                        'servicePoint:id,status',
                    ]),
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'draft_order_id',
                        'table_session_guest_id',
                        'menu_item_id',
                        'item_name',
                        'quantity',
                        'unit_price',
                        'modifier_total',
                        'total_price',
                        'selected_modifiers',
                        'comment',
                        'created_at',
                    ])
                    ->with([
                        'guest:id,guest_name',
                        'menuItem' => fn ($query) => $query
                            ->select(['id', 'kitchen_department_id'])
                            ->with([
                                'kitchenDepartment:id,branch_id,type,name',
                            ]),
                    ]),
                'order:id,draft_order_id,status',
            ])
            ->whereKey($draftOrder->id)
            ->firstOrFail();
    }

    private function ensureCanConfirm(DraftOrder $draftOrder, User $user): void
    {
        $tableSession = $draftOrder->tableSession;
        $branch = $tableSession?->branch;

        if ($tableSession === null || $branch === null) {
            throw ValidationException::withMessages([
                'draft_review' => __('Черновик больше не связан с открытым столом.'),
            ]);
        }

        $confirmableBranchIds = $this->resolveAccessibleBranchIds->handle($user, SystemPermission::ConfirmOrders);

        if (! $confirmableBranchIds->contains((int) $tableSession->branch_id)) {
            throw ValidationException::withMessages([
                'draft_review' => __('У вас нет права подтверждать заказы в этом филиале.'),
            ]);
        }

        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'draft_review' => __('Нельзя подтвердить заказ для закрытого стола.'),
            ]);
        }

        if ($draftOrder->status === DraftOrderStatus::ConvertedToOrder && $draftOrder->order instanceof Order) {
            return;
        }

        if (! in_array($draftOrder->status, [DraftOrderStatus::SentToWaiter, DraftOrderStatus::WaiterReview], true)) {
            throw ValidationException::withMessages([
                'draft_review' => __('Подтвердить можно только черновик, отправленный официанту.'),
            ]);
        }

        if ($draftOrder->items->isEmpty()) {
            throw ValidationException::withMessages([
                'draft_review' => __('Нельзя подтвердить пустой черновик.'),
            ]);
        }
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
