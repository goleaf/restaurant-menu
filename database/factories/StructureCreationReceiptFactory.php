<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\StructureCreationReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StructureCreationReceipt> */
class StructureCreationReceiptFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'actor_id' => fn (array $attributes): int => (int) Organization::query()->whereKey($attributes['organization_id'])->value('owner_user_id'),
            'request_key' => fake()->uuid(),
            'payload_hash' => hash('sha256', 'Fictional structure creation'),
            'kind' => 'organization',
            'resource_id' => fn (array $attributes): int => (int) $attributes['organization_id'],
        ];
    }
}
