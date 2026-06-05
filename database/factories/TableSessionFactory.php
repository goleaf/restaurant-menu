<?php

namespace Database\Factories;

use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSession>
 */
class TableSessionFactory extends Factory
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
            'opened_by_user_id' => null,
            'opened_by_guest_id' => null,
            'status' => TableSessionStatus::Pending,
            'source' => TableSessionSource::GuestCreated,
            'started_at' => null,
            'ended_at' => null,
            'closed_by_user_id' => null,
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

    public function waiterOpened(): static
    {
        return $this->state(fn (): array => [
            'source' => TableSessionSource::WaiterOpened,
            'opened_by_user_id' => User::factory(),
            'opened_by_guest_id' => null,
        ]);
    }

    public function guestCreated(): static
    {
        return $this->state(fn (): array => [
            'source' => TableSessionSource::GuestCreated,
            'opened_by_user_id' => null,
        ])->afterCreating(function (TableSession $tableSession): void {
            if ($tableSession->opened_by_guest_id !== null) {
                return;
            }

            $guest = TableSessionGuest::factory()
                ->for($tableSession)
                ->active()
                ->create();

            $tableSession->forceFill([
                'opened_by_guest_id' => $guest->id,
            ])->save();
        });
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionStatus::Active,
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    public function paymentRequested(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionStatus::PaymentRequested,
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionStatus::Paid,
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionStatus::Closed,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);
    }
}
