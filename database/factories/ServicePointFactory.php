<?php

namespace Database\Factories;

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePoint>
 */
class ServicePointFactory extends Factory
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
            'area_node_id' => null,
            'type' => fake()->randomElement(ServicePointType::values()),
            'name' => fake()->unique()->words(2, true),
            'display_number' => (string) fake()->numberBetween(1, 999),
            'internal_code' => fake()->unique()->bothify('SP-####'),
            'capacity' => fake()->numberBetween(1, 12),
            'icon' => null,
            'status' => ServicePointStatus::Free,
            'position_x' => fake()->randomFloat(2, 0, 1000),
            'position_y' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'status' => ServicePointStatus::Free,
            'is_active' => true,
        ]);
    }

    public function occupied(): static
    {
        return $this->state(fn (): array => [
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => [
            'status' => ServicePointStatus::Blocked,
            'is_active' => false,
        ]);
    }

    public function withQr(): static
    {
        return $this->afterCreating(function (ServicePoint $servicePoint): void {
            QrCode::factory()
                ->for($servicePoint)
                ->active()
                ->create();
        });
    }

    public function withoutQr(): static
    {
        return $this->afterCreating(function (ServicePoint $servicePoint): void {
            $servicePoint->qrCodes()->delete();
        });
    }
}
