<?php

namespace App\Actions\AreaNodes;

use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Order;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteAreaNodeAction
{
    public function handle(User $actor, Branch $branch, AreaNode $areaNode): void
    {
        DB::transaction(function () use ($actor, $branch, $areaNode): void {
            $scopedAreaNode = $branch->areaNodes()
                ->select([
                    'area_nodes.id',
                    'area_nodes.branch_id',
                    'area_nodes.parent_id',
                    'area_nodes.name',
                ])
                ->whereKey($areaNode->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $scopedAreaNode);

            $hasActiveOrder = Order::query()
                ->active()
                ->whereIn('service_point_id', ServicePoint::withTrashed()
                    ->select('id')
                    ->where('branch_id', $branch->id)
                    ->where('area_node_id', $scopedAreaNode->id))
                ->exists();

            if ($hasActiveOrder) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::StructureHasActiveOrder,
                    'structureDeletion',
                    context: [
                        'branch_id' => $branch->id,
                        'area_node_id' => $scopedAreaNode->id,
                    ],
                );
            }

            $scopedAreaNode->children()->update(['parent_id' => $scopedAreaNode->parent_id]);
            $scopedAreaNode->deleteOrFail();
        });
    }
}
