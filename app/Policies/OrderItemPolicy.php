<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

final class OrderItemPolicy
{
    public function __construct(
        private readonly OrderPolicy $orders,
    ) {}

    public function view(User $user, OrderItem $orderItem): bool
    {
        $order = $orderItem->order;

        return $order instanceof Order && $this->orders->view($user, $order);
    }

    public function cancel(User $user, OrderItem $orderItem): bool
    {
        $order = $orderItem->order;

        return $order instanceof Order && $this->orders->cancel($user, $order);
    }

    public function delete(User $user, OrderItem $orderItem): bool
    {
        return false;
    }

    public function forceDelete(User $user, OrderItem $orderItem): bool
    {
        return false;
    }
}
