<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\ServicePoints\CreateServicePointAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SaveOnboardingServicePointsAction
{
    public function __construct(
        private CreateServicePointAction $createServicePoint,
        private UpdateServicePointAction $updateServicePoint,
    ) {}

    /** @param array{tableCount: int, tablePrefix: string, tableCapacity: int} $data */
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
            $area = AreaNode::query()->select(['id', 'branch_id', 'name'])->where('branch_id', $branch->id)->whereKey($onboarding->area_node_id)->firstOrFail();
            Gate::forUser($user)->authorize('manageServicePoints', $branch);

            $points = $onboarding->servicePoints()
                ->withTrashed()
                ->select(['service_points.id', 'service_points.branch_id', 'service_points.area_node_id', 'service_points.type', 'service_points.name', 'service_points.display_number', 'service_points.capacity', 'service_points.deleted_at'])
                ->get();

            abort_if($points->contains(fn (ServicePoint $point): bool => (int) $point->branch_id !== (int) $branch->id), 404);

            $linkedPointCount = $onboarding->expectedServicePointCount($points->count());

            if ($data['tableCount'] < $linkedPointCount) {
                throw ValidationException::withMessages([
                    'form.tableCount' => __('ui.onboarding.restaurant_setup.validation.table_count_cannot_decrease'),
                ]);
            }

            $points = $points
                ->filter(fn (ServicePoint $point): bool => (int) $point->area_node_id === (int) $area->id || $point->area_node_id === null)
                ->values();

            foreach ($points as $point) {
                $point->setRelation('branch', $branch);

                if ($point->trashed()) {
                    Gate::forUser($user)->authorize('restoreCheckpointResource', [$onboarding, $point]);
                } else {
                    Gate::forUser($user)->authorize('update', $point);
                }
            }

            $onboarding->servicePoints()->detach();

            foreach ($points as $index => $point) {
                $position = $index + 1;
                $onboarding->servicePoints()->attach($point->id, ['position' => $position]);

                if ($point->trashed()) {
                    $point->restore();
                }

                $this->updateServicePoint->handle($point, $this->pointData($area, $data, $position), $user);
            }

            for ($position = $points->count() + 1; $position <= $data['tableCount']; $position++) {
                Gate::forUser($user)->authorize('create', [ServicePoint::class, $branch]);
                $point = $this->createServicePoint->handle($branch, $this->pointData($area, $data, $position));
                $onboarding->servicePoints()->attach($point->id, ['position' => $position]);
            }

            $onboarding->forceFill(['expected_service_point_count' => $data['tableCount']])->save();

            return $onboarding->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array{tableCount: int, tablePrefix: string, tableCapacity: int}  $data
     * @return array{area_node_id: int, type: string, name: string, display_number: string, capacity: int, icon: string, is_active: bool}
     */
    private function pointData(AreaNode $area, array $data, int $position): array
    {
        return [
            'area_node_id' => $area->id,
            'type' => ServicePointType::Table->value,
            'name' => $data['tablePrefix'].' '.$position,
            'display_number' => (string) $position,
            'capacity' => $data['tableCapacity'],
            'icon' => 'squares-2x2',
            'is_active' => true,
        ];
    }
}
