<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SaveOnboardingAreaAction
{
    public function __construct(private CreateAreaNodeAction $createArea) {}

    /** @param array{parent_id: null, type: string, name: string, icon: string|null, sort_order: int, is_active: bool} $data */
    public function handle(User $user, int $onboardingId, array $data): RestaurantOnboarding
    {
        return DB::transaction(function () use ($user, $onboardingId, $data): RestaurantOnboarding {
            $user = User::query()->whereKey($user->id)->firstOrFail();
            $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->whereKey($onboardingId)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $onboarding);
            abort_if($onboarding->branch_id === null, 409);
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'name'])
                ->where('organization_id', $onboarding->organization_id)
                ->where('brand_id', $onboarding->brand_id)
                ->whereHas('brand', fn ($query) => $query->where('organization_id', $onboarding->organization_id))
                ->whereKey($onboarding->branch_id)
                ->firstOrFail();
            $area = $onboarding->area_node_id === null ? null : $branch->areaNodes()
                ->withTrashed()
                ->select(['id', 'branch_id', 'parent_id', 'type', 'name', 'icon', 'sort_order', 'is_active', 'deleted_at', 'structure_version'])
                ->whereKey($onboarding->area_node_id)->firstOrFail();

            if ($area instanceof AreaNode) {
                Gate::forUser($user)->authorize('view', $area);
                if ($area->trashed() || $area->name !== $data['name'] || $area->type->value !== $data['type']) {
                    throw ValidationException::withMessages(['form.areaName' => __('center.use_rooms_editor')]);
                }
            } else {
                Gate::forUser($user)->authorize('create', [AreaNode::class, $branch]);
                $area = $this->createArea->handle($branch, $data, $user);
                if ($onboarding->forceFill(['area_node_id' => $area->id, 'setup_version' => $onboarding->setup_version + 1])->save() !== true) {
                    throw new \RuntimeException('The room checkpoint could not be saved.');
                }
            }

            return $onboarding->refresh();
        }, attempts: 3);
    }
}
