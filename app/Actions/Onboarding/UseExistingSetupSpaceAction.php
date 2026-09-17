<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class UseExistingSetupSpaceAction
{
    public function handle(User $actor, int $setupId, int $areaId, int $expectedVersion): RestaurantOnboarding
    {
        return DB::transaction(function () use ($actor, $setupId, $areaId, $expectedVersion): RestaurantOnboarding {
            $actor = User::query()->whereKey($actor->id)->firstOrFail();
            $setup = RestaurantOnboarding::query()->where('user_id', $actor->id)->whereKey($setupId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $setup);
            $branch = Branch::query()->where('organization_id', $setup->organization_id)->where('brand_id', $setup->brand_id)->whereKey($setup->branch_id)->firstOrFail();
            Gate::forUser($actor)->authorize('manageServicePoints', $branch);
            $area = AreaNode::query()->where('branch_id', $branch->id)->whereKey($areaId)->firstOrFail();
            if ($setup->area_node_id !== null && (int) $setup->area_node_id !== $area->id) {
                throw ValidationException::withMessages(['existingAreaId' => __('center.conflict')]);
            }
            $points = $branch->servicePoints()->where('area_node_id', $area->id)->where('type', ServicePointType::Table)->orderBy('id')->limit(BulkCreateServicePointsAction::MAX_RANGE_SIZE + 1)->get(['id']);
            if ($points->count() > BulkCreateServicePointsAction::MAX_RANGE_SIZE) {
                throw ValidationException::withMessages(['existingAreaId' => __('center.too_many_tables')]);
            }
            $links = [];
            foreach ($points as $index => $point) {
                $links[$point->id] = ['position' => $index + 1];
            }
            $currentLinks = $setup->servicePoints()->withTrashed()->limit(BulkCreateServicePointsAction::MAX_RANGE_SIZE + 1)->get(['service_points.id'])
                ->mapWithKeys(fn ($point): array => [$point->id => ['position' => (int) $point->getRelation('pivot')->getAttribute('position')]])->all();
            if ((int) $setup->area_node_id === $area->id && $setup->expected_service_point_count === $points->count() && $currentLinks === $links) {
                return $setup;
            }
            if ($setup->setup_version !== $expectedVersion) {
                throw ValidationException::withMessages(['existingAreaId' => __('center.conflict')]);
            }
            $setup->servicePoints()->detachOrFail();
            $setup->servicePoints()->attachOrFail($links);
            if ($setup->forceFill(['area_node_id' => $area->id, 'expected_service_point_count' => $points->count(), 'setup_version' => $setup->setup_version + 1])->save() !== true) {
                throw new \RuntimeException('The room checkpoint could not be saved.');
            }

            return $setup;
        }, attempts: 3);
    }
}
