<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Services\Onboarding\RestaurantSetupQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CompleteRestaurantPreparationAction
{
    public function __construct(private RestaurantSetupQueryService $queries) {}

    public function handle(User $actor, int $setupId): RestaurantOnboarding
    {
        return DB::transaction(function () use ($actor, $setupId): RestaurantOnboarding {
            $actor = User::query()->whereKey($actor->id)->firstOrFail();
            $setup = RestaurantOnboarding::query()->where('user_id', $actor->id)->whereKey($setupId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $setup);
            if ($setup->completed_at !== null) {
                return $setup;
            }
            $state = $this->queries->presentation($actor, $setupId);
            foreach ([3, 4, 5, 6, 7] as $step) {
                if (! $state['done'][$step]) {
                    throw ValidationException::withMessages(['preparation' => __('center.incomplete')]);
                }
            }
            if ($setup->forceFill(['completed_at' => now(), 'setup_version' => $setup->setup_version + 1])->save() !== true) {
                throw new \RuntimeException('The preparation checkpoint could not be saved.');
            }

            return $setup->refresh();
        }, attempts: 3);
    }
}
