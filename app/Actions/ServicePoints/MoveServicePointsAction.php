<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Floor\RunFloorOperationAction;
use App\Actions\ServicePoints\Support\ServicePointMutationGuard;
use App\Enums\AuditLogAction;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Branches\ServicePointQueryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class MoveServicePointsAction
{
    public function __construct(
        private readonly ServicePointMutationGuard $guard,
        private readonly ServicePointQueryService $queries,
        private readonly RunFloorOperationAction $operations,
        private readonly RecordAuditLogAction $audit,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array{rows: list<array{id: int, name: string, area_node_id: int|null, target_area_node_id: int|null}>, versions: array<int, int>, fingerprint: string}
     */
    public function preview(User $actor, Branch $branch, array $ids, ?int $targetAreaId): array
    {
        return DB::transaction(function () use ($actor, $branch, $ids, $targetAreaId): array {
            $user = $this->guard->actor($actor);
            $currentBranch = $this->guard->branch($branch->id);
            $points = $this->authorizedRows($user, $currentBranch, $ids);
            $state = $this->state($currentBranch, $points, $targetAreaId);
            foreach ($points as $point) {
                if ($point->area_node_id !== $targetAreaId) {
                    $this->guard->idle($point);
                }
            }

            return $state;
        }, 3);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, int>  $expectedVersions
     * @return array{moved_count: int, ids: list<int>}
     */
    public function handle(User $actor, Branch $branch, array $ids, ?int $targetAreaId, array $expectedVersions, string $expectedFingerprint, string $requestId): array
    {
        $authorize = function (User $user, Branch $currentBranch) use ($ids): void {
            $this->authorizedRows($user, $currentBranch, $ids);
        };

        return $this->operations->handle($actor, $branch, $requestId, 'service_point_move', null,
            ['ids' => $ids, 'target_area_id' => $targetAreaId, 'versions' => $expectedVersions, 'fingerprint' => $expectedFingerprint],
            $authorize, function (User $user, Branch $currentBranch) use ($ids, $targetAreaId, $expectedVersions, $expectedFingerprint): array {
                $points = $this->authorizedRows($user, $currentBranch, $ids);
                $state = $this->state($currentBranch, $points, $targetAreaId);
                if ($state['versions'] !== $expectedVersions || ! hash_equals($state['fingerprint'], $expectedFingerprint)) {
                    throw ValidationException::withMessages(['expectedFingerprint' => __('floor.errors.structure_changed')]);
                }
                foreach ($points as $point) {
                    if ($point->area_node_id !== $targetAreaId) {
                        $this->guard->idle($point);
                    }
                }
                $moved = [];
                foreach ($points as $point) {
                    if ($point->area_node_id === $targetAreaId) {
                        continue;
                    }
                    $before = $point->only(['area_node_id', 'name']);
                    $point->area_node_id = $targetAreaId;
                    if (! $point->save()) {
                        throw new RuntimeException('The service point could not be moved.');
                    }
                    $this->audit->handle(AuditLogAction::ServicePointMoved, 'service_point', $point->id,
                        actorUser: $user, organizationId: $currentBranch->organization_id, branchId: $currentBranch->id,
                        oldValues: $before, newValues: $point->only(['area_node_id', 'name']));
                    $moved[] = $point->id;
                }

                return ['moved_count' => count($moved), 'ids' => $moved];
            });
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, ServicePoint>
     */
    private function authorizedRows(User $actor, Branch $branch, array $ids): Collection
    {
        Gate::forUser($actor)->authorize('create', [ServicePoint::class, $branch]);
        $points = $this->queries->mutationRows($branch, $ids);
        if ($points->count() !== count($ids)) {
            throw ValidationException::withMessages(['servicePointIds' => __('floor.errors.selection_changed')]);
        }
        foreach ($points as $point) {
            $point->setRelation('branch', $branch);
            Gate::forUser($actor)->authorize('update', $point);
        }

        return $points;
    }

    /**
     * @param  Collection<int, ServicePoint>  $points
     * @return array{rows: list<array{id: int, name: string, area_node_id: int|null, target_area_node_id: int|null}>, versions: array<int, int>, fingerprint: string}
     */
    private function state(Branch $branch, Collection $points, ?int $targetAreaId): array
    {
        $area = $targetAreaId === null ? null : AreaNode::query()->select(['id', 'branch_id', 'structure_version'])
            ->where('branch_id', $branch->id)->whereKey($targetAreaId)->first();
        if ($targetAreaId !== null && $area === null) {
            throw ValidationException::withMessages(['targetAreaId' => __('errors.domain.selected_area_unavailable')]);
        }
        $rows = $points->map(static fn (ServicePoint $point): array => [
            'id' => $point->id, 'name' => $point->name, 'area_node_id' => $point->area_node_id, 'target_area_node_id' => $targetAreaId,
        ])->all();
        $versions = $points->mapWithKeys(static fn (ServicePoint $point): array => [$point->id => $point->structure_version])->all();

        return ['rows' => $rows, 'versions' => $versions,
            'fingerprint' => hash('sha256', json_encode([$branch->id, $targetAreaId, $area?->structure_version, $rows, $versions], JSON_THROW_ON_ERROR))];
    }
}
