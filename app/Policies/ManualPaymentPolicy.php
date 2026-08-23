<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Payments\ResolvePaymentAccessibleBranchIdsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\ManualPayment;
use App\Models\User;

final class ManualPaymentPolicy
{
    public function __construct(
        private readonly ResolvePaymentAccessibleBranchIdsAction $resolvePaymentAccess,
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->resolvePaymentAccess->canView($user, (int) $branch->id);
    }

    public function view(User $user, ManualPayment $manualPayment): bool
    {
        return $this->resolvePaymentAccess->canView($user, (int) $manualPayment->branch_id);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->resolvePaymentAccess->canManage($user, (int) $branch->id);
    }

    public function manage(User $user, ManualPayment $manualPayment): bool
    {
        return $this->resolvePaymentAccess->canManage($user, (int) $manualPayment->branch_id);
    }

    public function correct(User $user, ManualPayment $manualPayment): bool
    {
        return $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::CorrectPayments)
            ->contains((int) $manualPayment->branch_id);
    }

    public function update(User $user, ManualPayment $manualPayment): bool
    {
        return false;
    }

    public function delete(User $user, ManualPayment $manualPayment): bool
    {
        return false;
    }
}
