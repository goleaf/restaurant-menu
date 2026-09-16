<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu\Concerns;

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
            $this->scheduleForm->scheduleMenuId = (string) $menuId;
        }

        $validated = $this->scheduleForm->validated($this->branch);
        $menu = $this->catalogData->findBranchMenu($this->branch, $validated['menuId']);

        $createSchedule->handle($menu, [
            'day_of_week' => $validated['dayOfWeek'],
            'starts_at' => $validated['startsAt'],
            'ends_at' => $validated['endsAt'],
        ]);

        $this->scheduleForm->clearForMenu((string) $menu->id);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_schedule_saved'));
    }

    public function startEditingMenuSchedule(int $scheduleId): void
    {
        $this->authorizeMenuManagement();

        $schedule = $this->catalogData->findBranchMenuSchedule($this->branchId, $scheduleId);

        $this->editingScheduleId = $schedule->id;
        $this->editingScheduleForm->populate($schedule);
    }

    public function cancelMenuScheduleEditing(): void
    {
        $this->editingScheduleId = null;
        $this->editingScheduleForm->reset();
        $this->editingScheduleForm->resetValidation([
            'scheduleDayOfWeek',
            'scheduleStartsAt',
            'scheduleEndsAt',
        ]);
    }

    public function updateMenuSchedule(UpdateMenuAvailabilityScheduleAction $updateSchedule): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingScheduleId === null) {
            return;
        }

        $validated = $this->editingScheduleForm->validated($this->branch, editing: true);
        $schedule = $this->catalogData->findBranchMenuSchedule($this->branchId, $this->editingScheduleId);

        try {
            $updateSchedule->handle(
                $this->branch,
                $schedule,
                $validated['dayOfWeek'],
                $validated['startsAt'],
                $validated['endsAt'],
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $componentField = match ($field) {
                    'dayOfWeek' => 'scheduleDayOfWeek',
                    'startsAt' => 'scheduleStartsAt',
                    'endsAt' => 'scheduleEndsAt',
                    default => $field,
                };

                foreach ($messages as $message) {
                    $this->editingScheduleForm->addError($componentField, $message);
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

        $this->scheduleForm->clearForMenu($menuId);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_schedule_removed'));
    }
}
