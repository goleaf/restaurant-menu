<?php

namespace App\Actions\AuditLogs;

use App\Actions\AuditLogs\Support\AuditLogValueSanitizer;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class BuildAuditLogIndexAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly AuditLogValueSanitizer $valueSanitizer,
    ) {}

    /**
     * @return array{has_access: bool, logs: CursorPaginator<int, array<string, mixed>>, branch_count: int}
     */
    public function handle(User $user, int $perPage = 50): array
    {
        $organizationIds = $this->accessibleOrganizationIds($user);
        $branchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ViewAuditLog);
        $perPage = max(10, min(100, $perPage));

        if (! $user->isSuperadmin() && $organizationIds->isEmpty()) {
            return [
                'has_access' => false,
                'logs' => $this->emptyPaginator($perPage),
                'branch_count' => 0,
            ];
        }

        $logs = AuditLog::query()
            ->select([
                'id',
                'organization_id',
                'branch_id',
                'user_id',
                'guest_id',
                'guest_display_name',
                'action',
                'entity_type',
                'entity_id',
                'old_values',
                'new_values',
                'created_at',
            ])
            ->with([
                'organization:id,name',
                'branch:id,name',
                'user:id,name,email',
                'guest:id,guest_name',
            ])
            ->when(! $user->isSuperadmin(), function ($query) use ($organizationIds, $branchIds): void {
                $query->where(function ($accessQuery) use ($organizationIds, $branchIds): void {
                    if ($branchIds->isNotEmpty()) {
                        $accessQuery->whereIn('branch_id', $branchIds);
                    }

                    $accessQuery->orWhere(function ($organizationQuery) use ($organizationIds): void {
                        $organizationQuery
                            ->whereNull('branch_id')
                            ->whereIn('organization_id', $organizationIds);
                    });
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->cursorPaginate(
                $perPage,
                [
                    'id',
                    'organization_id',
                    'branch_id',
                    'user_id',
                    'guest_id',
                    'guest_display_name',
                    'action',
                    'entity_type',
                    'entity_id',
                    'old_values',
                    'new_values',
                    'created_at',
                ],
                'auditLogsCursor',
            )
            ->through(fn (AuditLog $auditLog): array => $this->row($auditLog));

        return [
            'has_access' => true,
            'logs' => $logs,
            'branch_count' => $user->isSuperadmin()
                ? Branch::query()->count()
                : $branchIds->count(),
        ];
    }

    public function userHasAccess(User $user): bool
    {
        return $user->isSuperadmin()
            || $this->accessibleOrganizationIds($user)->isNotEmpty();
    }

    /**
     * @return Collection<int, int>
     */
    private function accessibleOrganizationIds(User $user): Collection
    {
        if ($user->isSuperadmin()) {
            return Organization::query()
                ->select(['id'])
                ->orderBy('id')
                ->pluck('id');
        }

        $permission = Permission::query()
            ->select(['id', 'code'])
            ->where('code', SystemPermission::ViewAuditLog->value)
            ->first();

        if (! $permission instanceof Permission) {
            return collect();
        }

        $override = $user->permissionOverrides()
            ->where('permissions.id', $permission->id)
            ->first();

        if ($override instanceof Permission && ! (bool) $override->pivot->enabled) {
            return collect();
        }

        return OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->when(! $override instanceof Permission, function ($query) use ($permission): void {
                $query->whereHas('role.permissions', function ($permissionQuery) use ($permission): void {
                    $permissionQuery
                        ->where('permissions.id', $permission->id)
                        ->where('permission_role.enabled', true);
                });
            })
            ->orderBy('organization_id')
            ->pluck('organization_id')
            ->unique()
            ->values();
    }

    /**
     * @return CursorPaginator<int, array<string, mixed>>
     */
    private function emptyPaginator(int $perPage): CursorPaginator
    {
        return new CursorPaginator([], $perPage);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AuditLog $auditLog): array
    {
        return [
            'id' => $auditLog->id,
            'created_at' => $auditLog->created_at?->format('Y-m-d H:i:s'),
            'action' => $auditLog->action->value,
            'action_label' => $auditLog->action->label(),
            'entity_type' => $auditLog->entity_type,
            'entity_id' => $auditLog->entity_id,
            'organization_name' => $auditLog->organization?->name,
            'branch_name' => $auditLog->branch?->name,
            'actor' => $auditLog->user?->name
                ?? $auditLog->guest_display_name
                ?? $auditLog->guest?->guest_name
                ?? 'System',
            'old_values' => $auditLog->old_values ?? [],
            'new_values' => $auditLog->new_values ?? [],
            'old_summary' => $this->valueSanitizer->summary($auditLog->old_values ?? []),
            'new_summary' => $this->valueSanitizer->summary($auditLog->new_values ?? []),
        ];
    }
}
