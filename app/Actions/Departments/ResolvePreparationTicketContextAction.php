<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\Models\KitchenTicket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ResolvePreparationTicketContextAction
{
    /**
     * @param  list<int>  $accessibleDepartmentIds
     * @return array{department_id: int, ticket_id: int}
     */
    public function handle(User $user, int $ticketId, int $branchId, array $accessibleDepartmentIds): array
    {
        $ticket = KitchenTicket::query()->select(['id', 'branch_id', 'kitchen_department_id'])
            ->where('branch_id', $branchId)
            ->whereIn('kitchen_department_id', $accessibleDepartmentIds)
            ->findOrFail($ticketId);
        Gate::forUser($user)->authorize('view', $ticket);

        return ['department_id' => (int) $ticket->kitchen_department_id, 'ticket_id' => $ticket->id];
    }
}
