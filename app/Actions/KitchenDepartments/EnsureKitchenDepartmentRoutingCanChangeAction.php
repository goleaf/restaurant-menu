<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Models\KitchenDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class EnsureKitchenDepartmentRoutingCanChangeAction
{
    public function handle(KitchenDepartment $department, bool $preserveHistory = false): void
    {
        $tickets = $department->kitchenTickets()->select(['kitchen_tickets.id']);
        if ($preserveHistory) {
            if ($tickets->exists()) {
                throw ValidationException::withMessages([
                    'kitchenDepartment.'.$department->id => __('preparation.department.history'),
                ]);
            }

            return;
        }

        if ($tickets
            ->whereHas('order', fn (Builder $orders): Builder => $orders->where('status', '!=', OrderStatus::Cancelled->value))
            ->whereHas('items', fn (Builder $items): Builder => $items
                ->whereNull('served_at')
                ->where('status', '!=', KitchenTicketItemStatus::Cancelled->value))
            ->exists()) {
            throw ValidationException::withMessages([
                'kitchenDepartment.'.$department->id => __('preparation.department.active_work'),
            ]);
        }
    }
}
