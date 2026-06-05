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

    public function bellaPizzaOldTown(Brand $brand): static
    {
        return $this->demoBranch($brand, [
            'name' => 'Bella Pizza Old Town',
            'public_name' => 'Bella Pizza Old Town',
            'public_description' => 'Classic pizza restaurant in the demo old town branch.',
            'address' => 'Pilies g. 10',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => '+370 600 10001',
            'email' => 'old-town@bella-pizza.demo.test',
            'website_url' => 'https://bella-pizza.demo.test/old-town',
        ]);
    }

    public function bellaPizzaTerrace(Brand $brand): static
    {
        return $this->demoBranch($brand, [
            'name' => 'Bella Pizza Terrace',
            'public_name' => 'Bella Pizza Terrace',
            'public_description' => 'Open-air pizza terrace for QR ordering and table service checks.',
            'address' => 'Gedimino pr. 20',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => '+370 600 10002',
            'email' => 'terrace@bella-pizza.demo.test',
            'website_url' => 'https://bella-pizza.demo.test/terrace',
        ]);
    }

    public function sushiMasterCenter(Brand $brand): static
    {
        return $this->demoBranch($brand, [
            'name' => 'Sushi Master Center',
            'public_name' => 'Sushi Master Center',
            'public_description' => 'Compact sushi branch for kitchen department and pickup flow checks.',
            'address' => 'Konstitucijos pr. 12',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => '+370 600 20001',
            'email' => 'center@sushi-master.demo.test',
            'website_url' => 'https://sushi-master.demo.test/center',
        ]);
    }

    public function coffeeBarSmallHall(Brand $brand): static
    {
        return $this->demoBranch($brand, [
            'name' => 'Coffee Bar Small Hall',
            'public_name' => 'Coffee Bar Small Hall',
            'public_description' => 'Small coffee bar branch for bar seats and quick payment checks.',
            'address' => 'Vokieciu g. 5',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => '+370 600 30001',
            'email' => 'small-hall@coffee-bar.demo.test',
            'website_url' => 'https://coffee-bar.demo.test/small-hall',
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function demoBranch(Brand $brand, array $attributes): static
    {
        return $this
            ->forBrand($brand)
            ->active()
            ->state(fn (): array => [
                ...$attributes,
                'logo_path' => null,
                'cover_image_path' => null,
                'instagram_url' => null,
                'facebook_url' => null,
                'tiktok_url' => null,
            ]);
    }
}
