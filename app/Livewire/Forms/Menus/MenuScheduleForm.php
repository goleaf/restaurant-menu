<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use App\Models\Branch;
use App\Models\MenuAvailabilitySchedule;
use App\Support\Validation\Menus\MenuFieldLabels;
use App\Support\Validation\Menus\MenuScheduleRules;
use App\Support\Validation\Menus\MenuScopeRules;
use Livewire\Form;

final class MenuScheduleForm extends Form
{
    public mixed $scheduleMenuId = '';

    public mixed $scheduleDayOfWeek = '1';

    public mixed $scheduleStartsAt = '08:00';

    public mixed $scheduleEndsAt = '12:00';

    /** @return array{menuId: ?int, dayOfWeek: int, startsAt: string, endsAt: string} */
    public function validated(Branch $branch, bool $editing = false): array
    {
        $rules = MenuScheduleRules::menuSchedule();
        if (! $editing) {
            $rules['scheduleMenuId'] = ['bail', 'required', 'numeric', 'integer', MenuScopeRules::menu($branch)];
        }
        $values = $this->validate($rules);

        return ['menuId' => $editing ? null : (int) $values['scheduleMenuId'], 'dayOfWeek' => (int) $values['scheduleDayOfWeek'], 'startsAt' => $values['scheduleStartsAt'], 'endsAt' => $values['scheduleEndsAt']];
    }

    public function populate(MenuAvailabilitySchedule $schedule): void
    {
        $this->scheduleMenuId = (string) $schedule->menu_id;
        $this->scheduleDayOfWeek = (string) $schedule->day_of_week;
        $this->scheduleStartsAt = substr((string) $schedule->starts_at, 0, 5);
        $this->scheduleEndsAt = substr((string) $schedule->ends_at, 0, 5);
    }

    public function clearForMenu(mixed $menuId): void
    {
        $this->reset();
        $this->scheduleMenuId = $menuId;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return MenuFieldLabels::forEditor('schedule');
    }
}
