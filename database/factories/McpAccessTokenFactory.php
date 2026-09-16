<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\McpAccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<McpAccessToken> */
class McpAccessTokenFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'organization_id' => fn (array $attributes): int => (int) Branch::query()->whereKey($attributes['branch_id'])->value('organization_id'),
            'name' => 'Fictional MCP client',
            'token_hash' => hash('sha256', random_bytes(32)),
            'abilities' => ['branch_context'],
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
