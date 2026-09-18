<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\FloorOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FloorOperation>
 */
class FloorOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'branch_id' => Branch::factory(),
            'actor_user_id' => User::factory(),
            'kind' => 'pause',
            'target_id' => null,
            'payload_hash' => hash('sha256', 'factory'),
            'result' => ['version' => 1],
        ];
    }
}
