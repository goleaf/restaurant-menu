<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchSettingsChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchSettingsChange>
 */
class BranchSettingsChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'request_id' => fake()->uuid(),
            'group' => 'profile',
            'payload_hash' => hash('sha256', 'Fictional settings change'),
            'result' => ['fingerprint' => hash('sha256', 'Fictional result')],
        ];
    }
}
