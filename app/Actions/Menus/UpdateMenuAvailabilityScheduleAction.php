<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\MenuAvailabilitySchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateMenuAvailabilityScheduleAction
{
    public function handle(
        Branch $branch,
        MenuAvailabilitySchedule $schedule,
        int $dayOfWeek,
        string $startsAt,
        string $endsAt,
    ): MenuAvailabilitySchedule {
        $validated = Validator::make([
            'dayOfWeek' => $dayOfWeek,
            'startsAt' => trim($startsAt),
            'endsAt' => trim($endsAt),
        ], [
            'dayOfWeek' => ['required', 'integer', 'min:1', 'max:7'],
            'startsAt' => ['required', 'date_format:H:i'],
            'endsAt' => ['required', 'date_format:H:i'],
        ])->validate();

        if ($validated['startsAt'] >= $validated['endsAt']) {
            throw ValidationException::withMessages([
                'endsAt' => __('menu.schedules.errors.end_after_start'),
            ]);
        }

        return DB::transaction(function () use ($branch, $schedule, $validated): MenuAvailabilitySchedule {
            $scopedSchedule = MenuAvailabilitySchedule::query()
                ->select(['id', 'menu_id', 'day_of_week', 'starts_at', 'ends_at', 'created_at', 'updated_at'])
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->whereKey($schedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            $overlaps = MenuAvailabilitySchedule::query()
                ->where('menu_id', $scopedSchedule->menu_id)
                ->where('day_of_week', (int) $validated['dayOfWeek'])
                ->whereKeyNot($scopedSchedule->id)
                ->where('starts_at', '<', $validated['endsAt'])
                ->where('ends_at', '>', $validated['startsAt'])
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'startsAt' => __('menu.schedules.errors.overlap'),
                ]);
            }

            $scopedSchedule->updateOrFail([
                'day_of_week' => (int) $validated['dayOfWeek'],
                'starts_at' => $validated['startsAt'],
                'ends_at' => $validated['endsAt'],
            ]);

            return $scopedSchedule->refresh();
        });
    }
}
