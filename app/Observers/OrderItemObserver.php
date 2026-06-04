<?php

namespace App\Observers;

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Models\Order;
use App\Models\OrderItem;

class OrderItemObserver
{
    /**
     * Handle the OrderItem "created" event.
     */
    public function created(OrderItem $orderItem): void
    {
        $this->forgetAnalytics($orderItem);
    }

    /**
     * Handle the OrderItem "updated" event.
     */
    public function updated(OrderItem $orderItem): void
    {
        $this->forgetAnalytics($orderItem);
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $orderItem): void
    {
        $this->forgetAnalytics($orderItem);
    }

    /**
     * Handle the OrderItem "restored" event.
     */
    public function restored(OrderItem $orderItem): void
    {
        $this->forgetAnalytics($orderItem);
    }

    /**
     * Handle the OrderItem "force deleted" event.
     */
    public function forceDeleted(OrderItem $orderItem): void
    {
        $this->forgetAnalytics($orderItem);
    }

    private function forgetAnalytics(OrderItem $orderItem): void
    {
        $this->forgetForOrderId((int) $orderItem->order_id);

        $originalOrderId = (int) $orderItem->getOriginal('order_id');

        if ($originalOrderId > 0 && $originalOrderId !== (int) $orderItem->order_id) {
            $this->forgetForOrderId($originalOrderId);
        }
    }

    private function forgetForOrderId(int $orderId): void
    {
        if ($orderId < 1) {
            return;
        }

        $branchId = Order::query()
            ->select(['id', 'branch_id'])
            ->whereKey($orderId)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            BuildBasicAnalyticsDashboardAction::forgetForBranch((int) $branchId);
            BuildRestaurantDashboardAction::forgetForBranch((int) $branchId);
        }
    }
}
