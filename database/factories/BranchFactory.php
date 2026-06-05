<?php

namespace Database\Factories;

use App\Enums\KitchenDepartmentType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\ServicePoint;
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

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $brand->organization_id,
            'brand_id' => $brand->id,
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

    public function withDepartments(int $count = 1): static
    {
        return $this->afterCreating(function (Branch $branch) use ($count): void {
            $types = KitchenDepartmentType::cases();

            for ($index = 0; $index < $count; $index++) {
                $type = $types[$index % count($types)];

                KitchenDepartment::factory()
                    ->for($branch)
                    ->create([
                        'type' => $type,
                        'name' => $type->label(),
                        'sort_order' => ($index + 1) * 10,
                    ]);
            }
        });
    }

    public function withAreaNodes(int $count = 1): static
    {
        return $this->afterCreating(function (Branch $branch) use ($count): void {
            AreaNode::factory()
                ->count($count)
                ->for($branch)
                ->create();
        });
    }

    public function withServicePoints(int $count = 1): static
    {
        return $this->afterCreating(function (Branch $branch) use ($count): void {
            ServicePoint::factory()
                ->count($count)
                ->for($branch)
                ->create();
        });
    }

    public function withMenus(int $count = 1): static
    {
        return $this->afterCreating(function (Branch $branch) use ($count): void {
            Menu::factory()
                ->count($count)
                ->for($branch)
                ->create();
        });
    }
}
