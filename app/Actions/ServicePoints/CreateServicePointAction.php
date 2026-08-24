<?php

namespace App\Actions\ServicePoints;

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\Branch;
use App\Models\ServicePoint;
use Illuminate\Support\Str;

class CreateServicePointAction
{
    public function __construct(
        private readonly EnsureAreaNodeBelongsToBranchAction $ensureAreaNodeBelongsToBranch,
    ) {}

    /**
     * @param  array{area_node_id: int|null, type: string, name: string, display_number: string|null, capacity: int, icon: string|null, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data): ServicePoint
    {
        $this->ensureAreaNodeBelongsToBranch->handle($branch->id, $data['area_node_id']);

        $servicePoint = $branch->servicePoints()->make([
            'area_node_id' => $data['area_node_id'],
            'type' => ServicePointType::from($data['type']),
            'name' => $data['name'],
            'display_number' => $data['display_number'],
            'internal_code' => 'SP-'.Str::upper((string) Str::ulid()),
            'capacity' => $data['capacity'],
            'icon' => $data['icon'],
            'is_active' => $data['is_active'],
            'metadata' => [],
        ]);
        $servicePoint->forceFill([
            'status' => ServicePointStatus::Free,
        ])->save();

        return $servicePoint;
    }
}
