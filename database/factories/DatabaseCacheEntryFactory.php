<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DatabaseCacheEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatabaseCacheEntry>
 */
class DatabaseCacheEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => config('cache.prefix').'analytics:dashboard:factory:'.fake()->uuid(),
            'value' => serialize(['fixture' => true]),
            'expiration' => now()->addMinutes(5)->getTimestamp(),
        ];
    }
}
