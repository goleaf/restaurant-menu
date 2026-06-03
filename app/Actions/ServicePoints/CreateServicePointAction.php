<?php

namespace App\Actions\ServicePoints;

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateServicePointAction
{
    /**
     * @param  array{area_node_id: int|null, type: string, name: string, display_number: string|null, capacity: int, icon: string|null, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data): ServicePoint
    {
        $this->ensureAreaNodeBelongsToBranch($branch, $data['area_node_id']);

        return $branch->servicePoints()->create([
            'area_node_id' => $data['area_node_id'],
            'type' => ServicePointType::from($data['type']),
            'name' => $data['name'],
            'display_number' => $data['display_number'],
            'internal_code' => 'SP-'.Str::upper((string) Str::ulid()),
            'capacity' => $data['capacity'],
            'icon' => $data['icon'],
            'status' => ServicePointStatus::Free,
            'is_active' => $data['is_active'],
            'metadata' => [],
        ]);
    }

    private function ensureAreaNodeBelongsToBranch(Branch $branch, ?int $areaNodeId): void
    {
        if ($areaNodeId === null) {
            return;
        }

        $areaNodeExists = AreaNode::query()
            ->whereKey($areaNodeId)
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $areaNodeExists) {
            throw new InvalidArgumentException('The selected area is not available.');
        }
    }
}
