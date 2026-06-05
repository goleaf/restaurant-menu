<?php

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
            'table_session_id' => TableSession::factory(),
            'service_point_id' => ServicePoint::factory(),
            'active_service_point_id' => fn (array $attributes): int => (int) $attributes['service_point_id'],
            'linked_by_user_id' => User::factory(),
            'linked_at' => now(),
            'unlinked_by_user_id' => null,
            'unlinked_at' => null,
        ];
    }
}
