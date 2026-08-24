<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints;

use App\Models\AreaNode;
use InvalidArgumentException;

final class EnsureAreaNodeBelongsToBranchAction
{
    public function handle(int $branchId, ?int $areaNodeId): void
    {
        if ($areaNodeId === null) {
            return;
        }

        $areaNodeExists = AreaNode::query()
            ->whereKey($areaNodeId)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $areaNodeExists) {
            throw new InvalidArgumentException('errors.domain.selected_area_unavailable');
        }
    }
}
