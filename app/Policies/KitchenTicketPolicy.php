<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Models\KitchenTicket;
use App\Models\User;

final class KitchenTicketPolicy
{
    public function __construct(
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $resolveKitchenDepartments,
        private readonly ResolveBarAccessibleDepartmentIdsAction $resolveBarDepartments,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->resolveKitchenDepartments->userHasAccess($user)
            || $this->resolveBarDepartments->userHasAccess($user);
    }

    public function view(User $user, KitchenTicket $kitchenTicket): bool
    {
        $departmentId = $kitchenTicket->kitchen_department_id;

        if ($departmentId === null) {
            return false;
        }

        return $this->resolveKitchenDepartments->handle($user)->contains((int) $departmentId)
            || $this->resolveBarDepartments->handle($user)->contains((int) $departmentId);
    }

    public function print(User $user, KitchenTicket $kitchenTicket): bool
    {
        return $this->view($user, $kitchenTicket);
    }

    public function updateStatus(User $user, KitchenTicket $kitchenTicket): bool
    {
        return $this->view($user, $kitchenTicket);
    }

    public function update(User $user, KitchenTicket $kitchenTicket): bool
    {
        return false;
    }

    public function delete(User $user, KitchenTicket $kitchenTicket): bool
    {
        return false;
    }
}
