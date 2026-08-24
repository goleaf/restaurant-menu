<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\TableSession;

final class CanRequestBillForTableSessionAction
{
    public function handle(TableSession $tableSession): bool
    {
        $hasUnsubmittedItems = DraftOrder::query()
            ->where('table_session_id', $tableSession->id)
            ->whereIn('status', [
                DraftOrderStatus::Draft->value,
                DraftOrderStatus::SentToWaiter->value,
                DraftOrderStatus::WaiterReview->value,
                DraftOrderStatus::Rejected->value,
            ])
            ->whereHas('items')
            ->exists();

        if ($hasUnsubmittedItems) {
            return false;
        }

        $orderStatuses = Order::query()
            ->select(['id', 'table_session_id', 'status'])
            ->where('table_session_id', $tableSession->id)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->orderBy('id')
            ->limit(500)
            ->pluck('status');

        if ($orderStatuses->isEmpty()) {
            return false;
        }

        return $orderStatuses->every(fn (OrderStatus|string $status): bool => in_array(
            $status instanceof OrderStatus ? $status : OrderStatus::from($status),
            [OrderStatus::Served, OrderStatus::PaymentRequested],
            true,
        ));
    }
}
