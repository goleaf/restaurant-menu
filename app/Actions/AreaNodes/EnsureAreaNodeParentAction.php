<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Models\AreaNode;
use App\Models\Branch;
use InvalidArgumentException;

final class EnsureAreaNodeParentAction
{
    public function handle(Branch $branch, ?int $parentId, ?int $areaId = null, bool $withDescendants = false): void
    {
        $visited = [];
        while ($parentId !== null) {
            if ($parentId === $areaId) {
                throw new InvalidArgumentException('errors.domain.area_cannot_move_into_child');
            }
            if (isset($visited[$parentId]) || count($visited) >= 63) {
                throw new InvalidArgumentException('floor.errors.invalid_hierarchy');
            }
            $visited[$parentId] = true;
            $parent = AreaNode::query()->select(['id', 'parent_id'])->where('branch_id', $branch->id)->whereKey($parentId)->first();
            if (! $parent instanceof AreaNode) {
                throw new InvalidArgumentException('errors.domain.selected_parent_area_unavailable');
            }
            $parentId = $parent->parent_id;
        }

        if ($withDescendants && $areaId !== null) {
            $this->ensureSubtreeDepth($branch, $areaId, count($visited));
        }
    }

    private function ensureSubtreeDepth(Branch $branch, int $areaId, int $ancestorDepth): void
    {
        $visited = [$areaId => true];
        $frontier = [$areaId];
        $depth = $ancestorDepth + 1;
        while ($frontier !== []) {
            $next = [];
            foreach (array_chunk($frontier, 200) as $parents) {
                $children = AreaNode::withTrashed()->select(['id'])->where('branch_id', $branch->id)
                    ->whereIn('parent_id', $parents)->where('id', '!=', $areaId)->lazyById(200);
                foreach ($children as $child) {
                    if ($depth >= 64 || isset($visited[$child->id]) || count($visited) >= 5000) {
                        throw new InvalidArgumentException('floor.errors.invalid_hierarchy');
                    }
                    $visited[$child->id] = true;
                    $next[] = $child->id;
                }
            }
            $frontier = $next;
            $depth++;
        }
    }
}
