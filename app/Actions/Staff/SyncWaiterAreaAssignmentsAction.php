<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SyncWaiterAreaAssignmentsAction
{
    /**
     * @param  list<int>  $areaNodeIds
     * @return list<int>
     */
    public function handle(Branch $branch, BranchUser $membership, User $assignedBy, array $areaNodeIds): array
    {
        Gate::forUser($assignedBy)->authorize('manageStaff', $branch);

        if ($membership->organization_id !== $branch->organization_id
            || $membership->branch_id !== $branch->id
            || ! Role::query()
                ->whereKey($membership->role_id)
                ->where('code', SystemRole::Waiter->value)
                ->exists()) {
            throw new InvalidArgumentException('Area assignments require a waiter membership in the selected branch.');
        }

        $organization = Organization::query()->whereKey($branch->organization_id)->firstOrFail();
        $waiterRole = Role::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($membership->role_id)
            ->firstOrFail();
        Gate::forUser($assignedBy)->authorize('assign', [$waiterRole, $organization]);

        $waiter = User::query()->select(['id'])->findOrFail($membership->user_id);
        $selectedIds = collect($areaNodeIds)
            ->map(static fn (int $id): int => $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $validIds = AreaNode::query()
            ->where('branch_id', $branch->id)
            ->whereIn('id', $selectedIds)
            ->pluck('id');

        if ($selectedIds->diff($validIds)->isNotEmpty()) {
            throw new InvalidArgumentException('One or more selected areas are unavailable.');
        }

        DB::transaction(function () use ($branch, $waiter, $assignedBy, $selectedIds): void {
            AreaNodeWaiter::query()
                ->where('organization_id', $branch->organization_id)
                ->where('branch_id', $branch->id)
                ->where('user_id', $waiter->id)
                ->when(
                    $selectedIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('area_node_id', $selectedIds),
                )
                ->delete();

            foreach ($selectedIds as $areaNodeId) {
                $assignment = AreaNodeWaiter::query()
                    ->where('area_node_id', $areaNodeId)
                    ->where('user_id', $waiter->id)
                    ->first() ?? new AreaNodeWaiter;

                $assignment->forceFill([
                    'organization_id' => $branch->organization_id,
                    'branch_id' => $branch->id,
                    'area_node_id' => $areaNodeId,
                    'user_id' => $waiter->id,
                    'assigned_by_user_id' => $assignedBy->id,
                    'assigned_at' => now(),
                ])->saveOrFail();
            }
        });

        return $selectedIds->all();
    }
}
