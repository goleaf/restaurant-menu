<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu\Concerns;

use App\Support\Validation\Menus\MenuScheduleRules;
use App\Actions\Menus\CreateMenuAvailabilityScheduleAction;
use App\Actions\Menus\DeleteMenuAvailabilityScheduleAction;
use App\Actions\Menus\UpdateMenuAvailabilityScheduleAction;
use Flux\Flux;
use Illuminate\Validation\ValidationException;

trait ManagesMenuSchedules
{
    public function createMenuSchedule(CreateMenuAvailabilityScheduleAction $createSchedule, ?int $menuId = null): void
    {
        $this->authorizeMenuManagement();

        if ($menuId !== null) {
            $this->scheduleMenuId = (string) $menuId;
        }

        $validated = $this->validate($this->menuScheduleRules());
        $menu = $this->catalogData->findBranchMenu($this->branch, (int) $validated['scheduleMenuId']);

        $createSchedule->handle($menu, [
            'day_of_week' => (int) $validated['scheduleDayOfWeek'],
            'starts_at' => $validated['scheduleStartsAt'],
            'ends_at' => $validated['scheduleEndsAt'],
        ]);

        $this->resetMenuScheduleForm((string) $menu->id);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_schedule_saved'));
    }

    public function startEditingMenuSchedule(int $scheduleId): void
    {
        $this->authorizeMenuManagement();

        $schedule = $this->catalogData->findBranchMenuSchedule($this->branchId, $scheduleId);

        $this->editingScheduleId = $schedule->id;
        $this->editingScheduleDayOfWeek = (string) $schedule->day_of_week;
        $this->editingScheduleStartsAt = substr((string) $schedule->starts_at, 0, 5);
        $this->editingScheduleEndsAt = substr((string) $schedule->ends_at, 0, 5);
    }

    public function cancelMenuScheduleEditing(): void
    {
        $this->editingScheduleId = null;
        $this->editingScheduleDayOfWeek = '1';
        $this->editingScheduleStartsAt = '08:00';
        $this->editingScheduleEndsAt = '12:00';
        $this->resetValidation([
            'editingScheduleDayOfWeek',
            'editingScheduleStartsAt',
            'editingScheduleEndsAt',
        ]);
    }

    public function updateMenuSchedule(UpdateMenuAvailabilityScheduleAction $updateSchedule): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingScheduleId === null) {
            return;
        }

        $validated = $this->validate($this->menuScheduleRules(editing: true));
        $schedule = $this->catalogData->findBranchMenuSchedule($this->branchId, $this->editingScheduleId);

        try {
            $updateSchedule->handle(
                $this->branch,
                $schedule,
                (int) $validated['editingScheduleDayOfWeek'],
                $validated['editingScheduleStartsAt'],
                $validated['editingScheduleEndsAt'],
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $componentField = match ($field) {
                    'dayOfWeek' => 'editingScheduleDayOfWeek',
                    'startsAt' => 'editingScheduleStartsAt',
                    'endsAt' => 'editingScheduleEndsAt',
                    default => $field,
                };

                foreach ($messages as $message) {
                    $this->addError($componentField, $message);
                }
            }

            return;
        }

        $this->cancelMenuScheduleEditing();
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('menu.schedules.messages.updated'));
    }

    public function deleteMenuSchedule(int $scheduleId, DeleteMenuAvailabilityScheduleAction $deleteSchedule): void
    {
        $this->authorizeMenuManagement();

        $schedule = $this->catalogData->findBranchMenuSchedule($this->branchId, $scheduleId);
        $menuId = (string) $schedule->menu_id;

        $deleteSchedule->handle($schedule);

        $this->resetMenuScheduleForm($menuId);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_schedule_removed'));
    }

    private function menuScheduleRules(bool $editing = false): array
    {
        if ($editing) {
            return [
                'editingScheduleDayOfWeek' => ['bail', 'required', 'numeric', 'integer', 'min:1', 'max:7'],
                'editingScheduleStartsAt' => ['required', 'date_format:H:i'],
                'editingScheduleEndsAt' => ['required', 'date_format:H:i'],
            ];
        }

        return [
            'scheduleMenuId' => ['bail', 'required', 'numeric', 'integer', $this->menuRule()],
            ...MenuScheduleRules::menuSchedule(),
        ];
    }

    private function resetMenuScheduleForm(?string $keepMenuId = null): void
    {
        $this->scheduleMenuId = $keepMenuId ?? $this->scheduleMenuId;
        $this->scheduleDayOfWeek = '1';
        $this->scheduleStartsAt = '08:00';
        $this->scheduleEndsAt = '12:00';
    }
}
