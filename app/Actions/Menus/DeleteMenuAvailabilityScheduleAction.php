<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\MenuAvailabilitySchedule;

final class DeleteMenuAvailabilityScheduleAction
{
    public function handle(MenuAvailabilitySchedule $schedule): void
    {
        $schedule->deleteOrFail();
    }
}
