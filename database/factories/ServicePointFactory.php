<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
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

    public function reserved(): static
    {
        return $this->withStatus(ServicePointStatus::Reserved);
    }

    public function waitingForWaiter(): static
    {
        return $this->withStatus(ServicePointStatus::WaitingWaiter);
    }

    public function withNewOrder(): static
    {
        return $this->withStatus(ServicePointStatus::HasNewOrder);
    }

    public function cooking(): static
    {
        return $this->withStatus(ServicePointStatus::Cooking);
    }

    public function readyToServe(): static
    {
        return $this->withStatus(ServicePointStatus::ReadyToServe);
    }

    public function paymentRequested(): static
    {
        return $this->withStatus(ServicePointStatus::PaymentRequested);
    }

    public function paid(): static
    {
        return $this->withStatus(ServicePointStatus::Paid);
    }

    public function closed(): static
    {
        return $this->withStatus(ServicePointStatus::Closed);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => [
            'status' => ServicePointStatus::Blocked,
            'is_active' => false,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => ServicePointStatus::Closed,
            'is_active' => false,
            'deleted_at' => now()->subDay(),
        ]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $branch->id,
        ]);
    }

    public function inAreaNode(AreaNode $areaNode): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $areaNode->branch_id,
            'area_node_id' => $areaNode->id,
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

    public function withActiveTableSession(): static
    {
        return $this->afterCreating(function (ServicePoint $servicePoint): void {
            TableSession::factory()
                ->forServicePoint($servicePoint)
                ->active()
                ->create();

            $servicePoint->forceFill([
                'status' => ServicePointStatus::Occupied,
            ])->save();
        });
    }

    private function withStatus(ServicePointStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'is_active' => $status !== ServicePointStatus::Blocked,
        ]);
    }
}
