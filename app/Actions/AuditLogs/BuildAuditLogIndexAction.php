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
use App\Models\PermissionUserOverride;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

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
        $perPage = (int) Number::clamp($perPage, 10, 100);

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

        $rows = PermissionUserOverride::query()->select(['id', 'permission_id', 'organization_id', 'scope_key', 'enabled'])
            ->where('user_id', $user->id)->where('permission_id', $permission->id)->get();
        $singleOrganization = $rows->contains(fn (PermissionUserOverride $row): bool => $row->organization_id === null && $row->enabled)
            && $user->organizationMemberships()->count() === 1;
        $memberships = OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)->where('status', OrganizationUserStatus::Active->value)
            ->whereHas('organization', fn ($query) => $query->whereDoesntHave('subscription')->orWhereHas('subscription', fn ($subscription) => $subscription->where('status', 'active')))
            ->with(['role.permissions' => fn ($query) => $query->where('permissions.id', $permission->id)])
            ->orderBy('organization_id')->get();

        return $memberships->filter(function (OrganizationUser $membership) use ($rows, $singleOrganization, $permission): bool {
            $overrides = PermissionUserOverride::effectiveForOrganization($rows, $membership->organization_id, $singleOrganization);

            return $overrides->has($permission->id) ? (bool) $overrides[$permission->id]
                : (bool) $membership->role?->permissions->contains(fn (Permission $candidate): bool => $candidate->getRelation('pivot') instanceof Pivot && (bool) $candidate->getRelation('pivot')->getAttribute('enabled'));
        })->pluck('organization_id')->unique()->values();
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
        $actor = match (true) {
            $auditLog->user_id !== null => $auditLog->user->name,
            filled($auditLog->guest_display_name) => $auditLog->guest_display_name,
            $auditLog->guest_id !== null => $auditLog->guest->guest_name,
            default => __('audit.actor.system'),
        };

        return [
            'id' => $auditLog->id,
            'created_at' => LocalizedDateFormatter::dateTime($auditLog->created_at),
            'action' => $auditLog->action->value,
            'action_label' => $auditLog->action->label(),
            'entity_type' => $auditLog->entity_type,
            'entity_id' => $auditLog->entity_id,
            'organization_name' => $auditLog->organization_id === null ? null : $auditLog->organization->name,
            'branch_name' => $auditLog->branch_id === null ? null : $auditLog->branch->name,
            'actor' => $actor,
            'old_values' => $auditLog->old_values ?? [],
            'new_values' => $auditLog->new_values ?? [],
            'old_summary' => $this->valueSanitizer->summary($auditLog->old_values ?? []),
            'new_summary' => $this->valueSanitizer->summary($auditLog->new_values ?? []),
        ];
    }
}
