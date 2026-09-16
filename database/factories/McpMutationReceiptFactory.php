<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\McpAbility;
use App\Models\McpAccessToken;
use App\Models\McpMutationReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpMutationReceipt>
 */
class McpMutationReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mcp_access_token_id' => McpAccessToken::factory(),
            'user_id' => fn (array $attributes): int => (int) McpAccessToken::query()->whereKey($attributes['mcp_access_token_id'])->value('user_id'),
            'organization_id' => fn (array $attributes): int => (int) McpAccessToken::query()->whereKey($attributes['mcp_access_token_id'])->value('organization_id'),
            'branch_id' => fn (array $attributes): int => (int) McpAccessToken::query()->whereKey($attributes['mcp_access_token_id'])->value('branch_id'),
            'idempotency_key' => fake()->uuid(),
            'ability' => McpAbility::SetOrderingPause,
            'input_hash' => hash('sha256', 'Fictional completed operation'),
            'result' => ['paused' => true],
        ];
    }
}
