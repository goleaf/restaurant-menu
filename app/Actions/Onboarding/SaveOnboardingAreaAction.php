<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\AreaNodes\UpdateAreaNodeAction;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SaveOnboardingAreaAction
{
    public function __construct(private CreateAreaNodeAction $createArea, private UpdateAreaNodeAction $updateArea) {}

    /** @param array{parent_id: null, type: string, name: string, icon: string|null, sort_order: int, is_active: bool} $data */
    public function handle(User $user, int $onboardingId, array $data): RestaurantOnboarding
    {
        return DB::transaction(function () use ($user, $onboardingId, $data): RestaurantOnboarding {
            $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->whereKey($onboardingId)->lockForUpdate()->firstOrFail();
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'name'])
                ->where('organization_id', $onboarding->organization_id)
                ->where('brand_id', $onboarding->brand_id)
                ->whereHas('brand', fn ($query) => $query->where('organization_id', $onboarding->organization_id))
                ->whereKey($onboarding->branch_id)
                ->firstOrFail();
            Gate::forUser($user)->authorize('update', $onboarding);
            $area = $onboarding->area_node_id === null ? null : $branch->areaNodes()
                ->withTrashed()
                ->select(['id', 'branch_id', 'parent_id', 'type', 'name', 'icon', 'sort_order', 'is_active', 'deleted_at'])
                ->whereKey($onboarding->area_node_id)->firstOrFail();

            if ($area instanceof AreaNode) {
                if ($area->trashed()) {
                    Gate::forUser($user)->authorize('restoreCheckpointResource', [$onboarding, $area]);
                    $area->restore();
                }

                $area->setRelation('branch', $branch);
                Gate::forUser($user)->authorize('update', $area);
                $this->updateArea->handle($area, $data);
            } else {
                Gate::forUser($user)->authorize('create', [AreaNode::class, $branch]);
                $area = $this->createArea->handle($branch, $data);
                $onboarding->forceFill(['area_node_id' => $area->id])->save();
            }

            return $onboarding->refresh();
        }, attempts: 3);
    }
}
