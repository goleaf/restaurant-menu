<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\AuditLog;
use App\Models\User;

final class AuditLogPolicy
{
    public function __construct(
        private readonly BuildAuditLogIndexAction $buildAuditLogIndex,
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->buildAuditLogIndex->userHasAccess($user);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($auditLog->branch_id !== null) {
            return $this->resolveAccessibleBranchIds
                ->handle($user, SystemPermission::ViewAuditLog)
                ->contains((int) $auditLog->branch_id);
        }

        if ($auditLog->organization_id === null) {
            return false;
        }

        return $user->canAccessOrganization((int) $auditLog->organization_id)
            && $user->hasPermission(SystemPermission::ViewAuditLog, (int) $auditLog->organization_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
