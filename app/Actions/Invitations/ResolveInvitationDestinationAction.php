<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\User;
use Illuminate\Support\Collection;

final class ResolveInvitationDestinationAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $waiterBranches,
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $kitchenDepartments,
        private readonly ResolveBarAccessibleDepartmentIdsAction $barDepartments,
        private readonly ResolveInvitationRecipientRoleAction $recipientRole,
    ) {}

    public function handle(Invitation $invitation, User $recipient): string
    {
        $role = $this->recipientRole->handle($invitation, $recipient);

        if (in_array($role, [SystemRole::Waiter, SystemRole::Cashier, SystemRole::ShiftManager], true)) {
            $branches = $this->waiterBranches->handle($recipient);
            if ($invitation->branch_id !== null && $branches->contains($invitation->branch_id)) {
                return route('restaurant.waiter.dashboard', ['branch' => $invitation->branch_id]);
            }
            if ($invitation->branch_id === null) {
                $branchId = Branch::query()->select(['id'])
                    ->where('organization_id', $invitation->organization_id)->whereIn('id', $branches)
                    ->orderBy('name')->orderBy('id')->first()?->id;
                if ($branchId !== null) {
                    return route('restaurant.waiter.dashboard', ['branch' => $branchId]);
                }
            }
        }
        if (in_array($role, [SystemRole::HeadChef, SystemRole::Cook], true)) {
            $departmentId = $this->departmentInScope($invitation, $this->kitchenDepartments->handle($recipient));
            if ($departmentId !== null) {
                return route('restaurant.kitchen.dashboard', ['department' => $departmentId]);
            }
        }
        if ($role === SystemRole::Bartender) {
            $departmentId = $this->departmentInScope($invitation, $this->barDepartments->handle($recipient));
            if ($departmentId !== null) {
                return route('restaurant.bar.dashboard', ['department' => $departmentId]);
            }
        }

        return route('restaurant.dashboard', $invitation->branch_id === null ? [] : ['branch' => $invitation->branch_id]);
    }

    /** @param Collection<int, int> $departmentIds */
    private function departmentInScope(Invitation $invitation, Collection $departmentIds): ?int
    {
        return KitchenDepartment::query()->select(['id'])->whereIn('id', $departmentIds)
            ->where('is_active', true)
            ->whereHas('branch', fn ($branch) => $branch->where('organization_id', $invitation->organization_id))
            ->when($invitation->branch_id !== null, fn ($departments) => $departments->where('branch_id', $invitation->branch_id))
            ->orderBy('branch_id')->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->first()?->id;
    }
}
