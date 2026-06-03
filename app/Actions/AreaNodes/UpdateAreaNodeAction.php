<?php

namespace App\Actions\AreaNodes;

use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class UpdateAreaNodeAction
{
    /**
     * @param  array{parent_id: int|null, type: string, name: string, icon: string|null, sort_order: int, is_active: bool}  $data
     */
    public function handle(AreaNode $areaNode, array $data): AreaNode
    {
        $this->ensureParentIsAvailable($areaNode, $data['parent_id']);

        $areaNode->fill([
            'parent_id' => $data['parent_id'],
            'type' => AreaNodeType::from($data['type']),
            'name' => $data['name'],
            'icon' => $data['icon'],
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);

        $areaNode->save();

        return $areaNode;
    }

    private function ensureParentIsAvailable(AreaNode $areaNode, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $areaNode->id) {
            throw new InvalidArgumentException('An area cannot be placed inside itself.');
        }

        $availableNodes = AreaNode::query()
            ->select(['id', 'parent_id'])
            ->where('branch_id', $areaNode->branch_id)
            ->whereNull('deleted_at')
            ->get();

        if (! $availableNodes->contains('id', $parentId)) {
            throw new InvalidArgumentException('The selected parent area is not available.');
        }

        if ($this->descendantIds($availableNodes, $areaNode->id)->contains($parentId)) {
            throw new InvalidArgumentException('An area cannot be placed inside its own child area.');
        }
    }

    /**
     * @param  EloquentCollection<int, AreaNode>  $nodes
     * @return Collection<int, int>
     */
    private function descendantIds(EloquentCollection $nodes, int $parentId): Collection
    {
        $children = $nodes->where('parent_id', $parentId);

        return $children
            ->pluck('id')
            ->merge($children->flatMap(fn (AreaNode $child): Collection => $this->descendantIds($nodes, $child->id)));
    }
}
