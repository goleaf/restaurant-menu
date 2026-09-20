<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Departments\ResolvePreparationAccessibleDepartmentIdsAction;
use App\Models\KitchenTicket;
use App\Models\User;

final class KitchenTicketPolicy
{
    public function __construct(
        private readonly ResolvePreparationAccessibleDepartmentIdsAction $resolveDepartments,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->resolveDepartments->handle($user)->isNotEmpty();
    }

    public function view(User $user, KitchenTicket $kitchenTicket): bool
    {
        $departmentId = $kitchenTicket->kitchen_department_id;

        if ($departmentId === null) {
            return false;
        }

        return $this->resolveDepartments->handle($user, (int) $kitchenTicket->branch_id)
            ->contains((int) $departmentId);
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
