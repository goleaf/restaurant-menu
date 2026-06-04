<?php

namespace Database\Factories;

use App\Enums\WaiterCallStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\WaiterCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaiterCall>
 */
class WaiterCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_point_id' => ServicePoint::factory(),
            'branch_id' => fn (array $attributes): int => ServicePoint::query()
                ->select(['id', 'branch_id'])
                ->whereKey($attributes['service_point_id'])
                ->firstOrFail()
                ->branch_id,
            'table_session_id' => fn (array $attributes): int => TableSession::factory()
                ->forServicePoint(ServicePoint::query()
                    ->select(['id', 'branch_id'])
                    ->whereKey($attributes['service_point_id'])
                    ->firstOrFail())
                ->active()
                ->create()
                ->id,
            'requested_by_guest_id' => fn (array $attributes): int => TableSessionGuest::factory()
                ->for(TableSession::query()
                    ->select(['id'])
                    ->whereKey($attributes['table_session_id'])
                    ->firstOrFail())
                ->create()
                ->id,
            'status' => WaiterCallStatus::Pending,
            'requested_at' => now(),
            'handled_at' => null,
            'handled_by_user_id' => null,
            'metadata' => [],
        ];
    }

    public function forServicePoint(ServicePoint $servicePoint): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $servicePoint->branch_id,
            'service_point_id' => $servicePoint->id,
        ]);
    }

    public function forTableSession(TableSession $tableSession): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $tableSession->branch_id,
            'service_point_id' => $tableSession->service_point_id,
            'table_session_id' => $tableSession->id,
        ]);
    }

    public function handled(): static
    {
        return $this->state(fn (): array => [
            'status' => WaiterCallStatus::Handled,
            'handled_at' => now(),
        ]);
    }
}
