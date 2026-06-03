<?php

namespace App\Actions\ServicePoints;

use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\ServicePoint;
use InvalidArgumentException;

class UpdateServicePointAction
{
    /**
     * @param  array{area_node_id: int|null, type: string, name: string, display_number: string|null, capacity: int, icon: string|null, is_active: bool}  $data
     */
    public function handle(ServicePoint $servicePoint, array $data): ServicePoint
    {
        $this->ensureAreaNodeBelongsToBranch($servicePoint, $data['area_node_id']);

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
}
