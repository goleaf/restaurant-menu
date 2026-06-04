<?php

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
}
