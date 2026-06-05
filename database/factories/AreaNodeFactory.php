<?php

namespace Database\Factories;

use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaNode>
 */
class AreaNodeFactory extends Factory
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
            'parent_id' => null,
            'type' => fake()->randomElement(AreaNodeType::values()),
            'name' => fake()->unique()->words(2, true),
            'icon' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $branch->id,
        ]);
    }

    public function withParent(?AreaNode $parent = null): static
    {
        return $this
            ->state(fn (): array => $parent instanceof AreaNode ? [
                'branch_id' => $parent->branch_id,
                'parent_id' => $parent->id,
            ] : [])
            ->afterCreating(function (AreaNode $areaNode) use ($parent): void {
                if ($parent instanceof AreaNode) {
                    return;
                }

                $createdParent = AreaNode::factory()
                    ->forBranch($areaNode->branch)
                    ->create(['parent_id' => null]);

                $areaNode->forceFill([
                    'parent_id' => $createdParent->id,
                ])->save();
            });
    }
}
