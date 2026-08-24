<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreAreaNodeAction
{
    public function handle(User $actor, Branch $branch, AreaNode $areaNode): void
    {
        DB::transaction(function () use ($actor, $branch, $areaNode): void {
            $scopedAreaNode = $branch->areaNodes()
                ->withTrashed()
                ->select([
                    'area_nodes.id',
                    'area_nodes.branch_id',
                    'area_nodes.parent_id',
                    'area_nodes.name',
                    'area_nodes.deleted_at',
                ])
                ->whereKey($areaNode->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('restore', $scopedAreaNode);
            $scopedAreaNode->restore();
        });
    }
}
