<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;

final class CreateMenuAvailabilityScheduleAction
{
    /**
     * @param  array{day_of_week: int, starts_at: string, ends_at: string}  $data
     */
    public function handle(Menu $menu, array $data): MenuAvailabilitySchedule
    {
        return $menu->availabilitySchedules()->create($data);
    }
}
