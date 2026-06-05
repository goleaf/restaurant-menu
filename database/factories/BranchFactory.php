<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'brand_id' => Brand::factory()->for($organization),
            'name' => fake()->company().' Branch',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
            'is_temporarily_closed' => false,
            'temporary_closed_reason' => null,
            'temporary_closed_until' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function withDefaultSettings(): static
    {
        return $this->afterCreating(function (Branch $branch): void {
            $settings = BranchSetting::query()
                ->where('branch_id', $branch->id)
                ->first();

            if (! $settings instanceof BranchSetting) {
                $settings = new BranchSetting;
                $settings->branch()->associate($branch);
                $settings->forceFill(BranchSetting::defaults($branch));
                $settings->save();
            }
        });
    }
}
