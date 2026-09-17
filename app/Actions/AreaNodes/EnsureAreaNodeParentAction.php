<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Models\AreaNode;
use App\Models\Branch;
use InvalidArgumentException;

final class EnsureAreaNodeParentAction
{
    public function handle(Branch $branch, ?int $parentId, ?int $areaId = null): void
    {
        $visited = [];
        while ($parentId !== null) {
            if ($parentId === $areaId) {
                throw new InvalidArgumentException('errors.domain.area_cannot_move_into_child');
            }
            if (isset($visited[$parentId]) || count($visited) >= 64) {
                throw new InvalidArgumentException('floor.errors.invalid_hierarchy');
            }
            $visited[$parentId] = true;
            $parent = AreaNode::query()->select(['id', 'parent_id'])->where('branch_id', $branch->id)->whereKey($parentId)->first();
            if (! $parent instanceof AreaNode) {
                throw new InvalidArgumentException('errors.domain.selected_parent_area_unavailable');
            }
            $parentId = $parent->parent_id;
        }
    }
}
