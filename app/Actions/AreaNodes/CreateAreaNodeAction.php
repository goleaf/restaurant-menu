<?php

namespace App\Actions\AreaNodes;

use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\Branch;
use InvalidArgumentException;

class CreateAreaNodeAction
{
    /**
     * @param  array{parent_id: int|null, type: string, name: string, icon: string|null, sort_order: int, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data): AreaNode
    {
        $this->ensureParentBelongsToBranch($branch, $data['parent_id']);

        return $branch->areaNodes()->create([
            'parent_id' => $data['parent_id'],
            'type' => AreaNodeType::from($data['type']),
            'name' => $data['name'],
            'icon' => $data['icon'],
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
            'metadata' => [],
        ]);
    }

    private function ensureParentBelongsToBranch(Branch $branch, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parentExists = AreaNode::query()
            ->whereKey($parentId)
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $parentExists) {
            throw new InvalidArgumentException('errors.domain.selected_parent_area_unavailable');
        }
    }
}
