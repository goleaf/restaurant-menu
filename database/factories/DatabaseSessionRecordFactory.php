<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DatabaseSessionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DatabaseSessionRecord>
 */
class DatabaseSessionRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::random(40),
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ];
    }
}
