<?php

namespace App\Actions\ServicePoints;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use InvalidArgumentException;

class UpdateServicePointAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /**
     * @param  array{area_node_id: int|null, type: string, name: string, display_number: string|null, capacity: int, icon: string|null, is_active: bool}  $data
     */
    public function handle(ServicePoint $servicePoint, array $data, ?User $updatedBy = null): ServicePoint
    {
        $this->ensureAreaNodeBelongsToBranch($servicePoint, $data['area_node_id']);

        $oldAreaNodeId = $servicePoint->area_node_id;

        $servicePoint->fill([
            'area_node_id' => $data['area_node_id'],
            'type' => ServicePointType::from($data['type']),
            'name' => $data['name'],
            'display_number' => $data['display_number'],
            'capacity' => $data['capacity'],
            'icon' => $data['icon'],
            'is_active' => $data['is_active'],
        ]);

        $servicePoint->save();

        if ($oldAreaNodeId !== $servicePoint->area_node_id) {
            $this->recordMove($servicePoint, $updatedBy, $oldAreaNodeId);
        }

        return $servicePoint;
    }

    private function ensureAreaNodeBelongsToBranch(ServicePoint $servicePoint, ?int $areaNodeId): void
    {
        if ($areaNodeId === null) {
            return;
        }

        $areaNodeExists = AreaNode::query()
            ->whereKey($areaNodeId)
            ->where('branch_id', $servicePoint->branch_id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $areaNodeExists) {
            throw new InvalidArgumentException('The selected area is not available.');
        }
    }

    private function recordMove(ServicePoint $servicePoint, ?User $updatedBy, ?int $oldAreaNodeId): void
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
                'name' => $servicePoint->name,
            ],
            newValues: [
                'area_node_id' => $servicePoint->area_node_id,
                'name' => $servicePoint->name,
            ],
        );
    }
}
