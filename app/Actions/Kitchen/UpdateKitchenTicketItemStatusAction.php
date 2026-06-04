<?php

namespace App\Actions\Kitchen;

use App\Enums\KitchenTicketItemStatus;
use App\Models\KitchenTicketItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateKitchenTicketItemStatusAction
{
    public function __construct(
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
    ) {}

    public function handle(KitchenTicketItem $item, KitchenTicketItemStatus $status, User $user): KitchenTicketItem
    {
        $item = KitchenTicketItem::query()
            ->select(['id', 'kitchen_ticket_id', 'status'])
            ->with([
                'kitchenTicket' => fn ($query) => $query->select(['id', 'kitchen_department_id']),
            ])
            ->whereKey($item->id)
            ->firstOrFail();

        $departmentId = $item->kitchenTicket?->kitchen_department_id;
        $accessibleDepartmentIds = $this->resolveAccessibleDepartmentIds->handle($user);

        if ($departmentId === null || ! $accessibleDepartmentIds->contains((int) $departmentId)) {
            throw ValidationException::withMessages([
                'ticket_item_status' => __('У вас нет доступа к этому кухонному тикету.'),
            ]);
        }

        $item->forceFill(['status' => $status])->save();

        return $item->refresh();
    }
}
