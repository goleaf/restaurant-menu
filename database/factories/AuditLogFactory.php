<?php

namespace Database\Factories;

use App\Enums\AuditLogAction;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'organization_id' => function (array $attributes): ?int {
                $branchId = $attributes['branch_id'] ?? null;

                if (! is_numeric($branchId)) {
                    return null;
                }

                return Branch::query()
                    ->select(['id', 'organization_id'])
                    ->whereKey((int) $branchId)
                    ->value('organization_id');
            },
            'user_id' => User::factory(),
            'guest_id' => null,
            'guest_token' => null,
            'action' => AuditLogAction::MenuPriceChanged,
            'entity_type' => 'menu_item',
            'entity_id' => fake()->numberBetween(1, 1000),
            'old_values' => ['price' => '10.00'],
            'new_values' => ['price' => '12.00'],
            'created_at' => now(),
        ];
    }
}
