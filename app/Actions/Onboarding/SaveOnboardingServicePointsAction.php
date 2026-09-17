<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\Validation\Branches\ServicePointRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SaveOnboardingServicePointsAction
{
    public function __construct(
        private CreateServicePointAction $createServicePoint,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $user, int $onboardingId, array $data): RestaurantOnboarding
    {
        $validated = Validator::make($data, ServicePointRules::onboardingServicePoints())->validate();
        $data = ['tableCount' => (int) $validated['tableCount'], 'tablePrefix' => $validated['tablePrefix'], 'tableCapacity' => (int) $validated['tableCapacity']];

        return DB::transaction(function () use ($user, $onboardingId, $data): RestaurantOnboarding {
            $user = User::query()->whereKey($user->id)->firstOrFail();
            $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->whereKey($onboardingId)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $onboarding);
            abort_if($onboarding->branch_id === null, 409);
            abort_if($onboarding->area_node_id === null, 409);
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'name'])
                ->where('organization_id', $onboarding->organization_id)
                ->where('brand_id', $onboarding->brand_id)
                ->whereHas('brand', fn ($query) => $query->where('organization_id', $onboarding->organization_id))
                ->whereKey($onboarding->branch_id)
                ->firstOrFail();
            $area = AreaNode::query()->select(['id', 'branch_id', 'name'])->where('branch_id', $branch->id)->whereKey($onboarding->area_node_id)->firstOrFail();
            Gate::forUser($user)->authorize('manageServicePoints', $branch);

            $points = $onboarding->servicePoints()
                ->withTrashed()
                ->select(['service_points.id', 'service_points.branch_id', 'service_points.area_node_id', 'service_points.type', 'service_points.name', 'service_points.display_number', 'service_points.capacity', 'service_points.deleted_at'])
                ->limit(BulkCreateServicePointsAction::MAX_RANGE_SIZE + 1)->get();

            abort_if($points->contains(fn (ServicePoint $point): bool => (int) $point->branch_id !== (int) $branch->id), 404);

            if ($points->isNotEmpty()) {
                $matches = $points->count() <= BulkCreateServicePointsAction::MAX_RANGE_SIZE
                    && $onboarding->hasCompleteServicePointSet($points, $branch->id, $area->id, $points->count())
                    && $points->count() === $data['tableCount'] && $points->every(fn (ServicePoint $point, int $index): bool => ! $point->trashed() && (int) $point->area_node_id === $area->id
                    && $point->name === $data['tablePrefix'].' '.($index + 1) && $point->capacity === $data['tableCapacity']);
                if (! $matches) {
                    throw ValidationException::withMessages(['form.tableCount' => __('center.use_rooms_editor')]);
                }

                return $onboarding;
            }

            for ($position = $points->count() + 1; $position <= $data['tableCount']; $position++) {
                Gate::forUser($user)->authorize('create', [ServicePoint::class, $branch]);
                $point = $this->createServicePoint->handle($branch, $this->pointData($area, $data, $position), $user);
                $onboarding->servicePoints()->attach($point->id, ['position' => $position]);
            }

            if ($onboarding->forceFill(['expected_service_point_count' => $data['tableCount'], 'setup_version' => $onboarding->setup_version + 1])->save() !== true) {
                throw new \RuntimeException('The table checkpoint could not be saved.');
            }

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
