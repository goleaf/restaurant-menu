<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Models\AreaNode;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class AreaNodeQueryService
{
    /** @return EloquentCollection<int, AreaNode> */
    public function forBranch(Branch $branch): EloquentCollection
    {
        return $branch->areaNodes()
            ->select($this->columns())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function findForBranch(Branch $branch, int $areaNodeId): AreaNode
    {
        return $branch->areaNodes()
            ->select($this->columns())
            ->whereKey($areaNodeId)
            ->firstOrFail();
    }

    /** @return list<string> */
    private function columns(): array
    {
        return [
            'id',
            'branch_id',
            'parent_id',
            'type',
            'name',
            'icon',
            'sort_order',
            'is_active',
            'metadata',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }
}
