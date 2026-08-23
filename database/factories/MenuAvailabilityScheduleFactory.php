<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuAvailabilitySchedule>
 */
class MenuAvailabilityScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'day_of_week' => fake()->numberBetween(1, 7),
            'starts_at' => '08:00',
            'ends_at' => '12:00',
        ];
    }

    public function weekday(int $dayOfWeek): static
    {
        return $this->state(fn (): array => [
            'day_of_week' => $dayOfWeek,
            'starts_at' => '11:00',
            'ends_at' => '15:00',
        ]);
    }

    public function weekend(int $dayOfWeek): static
    {
        return $this->state(fn (): array => [
            'day_of_week' => $dayOfWeek,
            'starts_at' => '12:00',
            'ends_at' => '23:00',
        ]);
    }
}
