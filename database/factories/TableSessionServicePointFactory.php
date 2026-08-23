<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSessionServicePoint>
 */
class TableSessionServicePointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_session_id' => TableSession::factory()->active(),
            'service_point_id' => fn (array $attributes): int => TableSession::query()
                ->select(['id', 'service_point_id'])
                ->whereKey($attributes['table_session_id'])
                ->firstOrFail()
                ->service_point_id,
            'active_service_point_id' => fn (array $attributes): int => (int) $attributes['service_point_id'],
            'linked_by_user_id' => User::factory(),
            'linked_at' => now(),
            'unlinked_by_user_id' => null,
            'unlinked_at' => null,
        ];
    }

    public function forTableSessionAndServicePoint(
        TableSession $tableSession,
        ServicePoint $servicePoint,
    ): static {
        if ($tableSession->branch_id !== $servicePoint->branch_id) {
            throw new \InvalidArgumentException('The service point must belong to the table session branch.');
        }

        return $this->state(fn (): array => [
            'table_session_id' => $tableSession->id,
            'service_point_id' => $servicePoint->id,
            'active_service_point_id' => $servicePoint->id,
        ]);
    }

    public function linked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active_service_point_id' => (int) $attributes['service_point_id'],
            'linked_at' => now(),
            'unlinked_by_user_id' => null,
            'unlinked_at' => null,
        ]);
    }

    public function unlinkedBy(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'active_service_point_id' => null,
            'unlinked_by_user_id' => $user instanceof User ? $user->id : User::factory(),
            'unlinked_at' => now(),
        ]);
    }
}
