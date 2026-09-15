<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemRole;
use App\Models\Invitation;
use App\Models\User;

final class ResolveInvitationDestinationAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $waiterBranches,
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $kitchenDepartments,
        private readonly ResolveBarAccessibleDepartmentIdsAction $barDepartments,
    ) {}

    public function handle(Invitation $invitation, User $recipient): string
    {
        $invitation->loadMissing('role:id,code');
        $role = $invitation->role?->code;

        if (in_array($role, [SystemRole::Waiter, SystemRole::Cashier, SystemRole::ShiftManager], true)) {
            $branches = $this->waiterBranches->handle($recipient);
            if ($invitation->branch_id !== null && $branches->contains($invitation->branch_id)) {
                return route('restaurant.waiter.dashboard', ['branch' => $invitation->branch_id]);
            }
            if ($invitation->branch_id === null && $branches->isNotEmpty()) {
                return route('restaurant.waiter.dashboard');
            }
        }
        if (in_array($role, [SystemRole::HeadChef, SystemRole::Cook], true) && $this->kitchenDepartments->userHasAccess($recipient)) {
            return route('restaurant.kitchen.dashboard');
        }
        if ($role === SystemRole::Bartender && $this->barDepartments->userHasAccess($recipient)) {
            return route('restaurant.bar.dashboard');
        }

        return route('restaurant.dashboard', $invitation->branch_id === null ? [] : ['branch' => $invitation->branch_id]);
    }
}
