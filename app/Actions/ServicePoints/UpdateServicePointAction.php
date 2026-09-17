<?php

namespace App\Actions\ServicePoints;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\ServicePoints\Support\ServicePointMutationGuard;
use App\Enums\AuditLogAction;
use App\Enums\ServicePointType;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Branches\ServicePointQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class UpdateServicePointAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly EnsureAreaNodeBelongsToBranchAction $ensureAreaNodeBelongsToBranch,
        private readonly ServicePointMutationGuard $guard,
        private readonly ValidateServicePointInputAction $validateInput,
        private readonly ServicePointQueryService $queries,
    ) {}

    /**
     * @param  array{area_node_id: int|null, type: string, name: string, display_number: string|null, capacity: int, icon: string|null, is_active: bool}  $data
     */
    public function handle(ServicePoint $servicePoint, array $data, ?User $updatedBy = null, ?int $expectedVersion = null): ServicePoint
    {
        return DB::transaction(function () use ($servicePoint, $data, $updatedBy, $expectedVersion): ServicePoint {
            $actor = $this->guard->actor($updatedBy);
            $branch = $this->guard->branch($servicePoint->branch_id);
            $point = $this->queries->mutationRow($branch, $servicePoint->id);
            $point->setRelation('branch', $branch);
            Gate::forUser($actor)->authorize('update', $point);
            $this->guard->version($point, $expectedVersion);
            $data = $this->validateInput->handle($data);
            $this->ensureAreaNodeBelongsToBranch->handle($branch->id, $data['area_node_id']);
            $before = $point->only(['area_node_id', 'type', 'name', 'display_number', 'capacity', 'icon', 'is_active']);
            $oldAreaNodeId = $point->area_node_id;
            $point->fill([
                'area_node_id' => $data['area_node_id'],
                'type' => ServicePointType::from($data['type']),
                'name' => $data['name'],
                'display_number' => $data['display_number'],
                'capacity' => $data['capacity'],
                'icon' => $data['icon'],
                'is_active' => $data['is_active'],
            ]);
            if ($point->isDirty('area_node_id') || ($point->isDirty('is_active') && ! $point->is_active)) {
                $this->guard->idle($point);
            }
            if (! $point->isDirty()) {
                return $point;
            }
            if (! $point->save()) {
                throw new RuntimeException('The service point could not be saved.');
            }
            if ($oldAreaNodeId !== $point->area_node_id) {
                $this->recordMove($point, $actor, $oldAreaNodeId, $before['name']);
            } else {
                $this->recordAuditLog->handle(AuditLogAction::ServicePointChanged, 'service_point', $point->id,
                    actorUser: $actor, organizationId: $branch->organization_id, branchId: $branch->id,
                    oldValues: $before, newValues: [...$point->only(array_keys($before)), 'kind' => 'updated']);
            }

            return $point;
        }, 3);
    }

    private function recordMove(ServicePoint $servicePoint, ?User $updatedBy, ?int $oldAreaNodeId, string $oldName): void
    {
        $branch = Branch::query()
            ->select(['id', 'organization_id'])
            ->whereKey($servicePoint->branch_id)
            ->first();

        $this->recordAuditLog->handle(
            action: AuditLogAction::ServicePointMoved,
            entityType: 'service_point',
            entityId: $servicePoint->id,
            actorUser: $updatedBy,
            organizationId: $branch?->organization_id,
            branchId: $servicePoint->branch_id,
            oldValues: [
                'area_node_id' => $oldAreaNodeId,
                'name' => $oldName,
            ],
            newValues: [
                'area_node_id' => $servicePoint->area_node_id,
                'name' => $servicePoint->name,
            ],
        );
    }
}
