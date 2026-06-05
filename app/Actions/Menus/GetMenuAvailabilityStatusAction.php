<?php

namespace App\Actions\Menus;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetMenuAvailabilityStatusAction
{
    /**
     * @return array{is_configured: bool, is_available: bool, label: string, detail: string, tone: string, next_available_at: string|null, available_until: string|null, timezone: string}
     */
    public function handle(Menu $menu, ?CarbonInterface $now = null): array
    {
        $branch = $this->branchFor($menu);
        $timezone = $this->timezoneFor($branch);
        $currentTime = $now instanceof CarbonInterface
            ? Carbon::instance($now->toDateTime())->setTimezone($timezone)
            : now($timezone);
        $schedules = $this->schedulesFor($menu);

        if ($schedules->isEmpty()) {
            return [
                'is_configured' => false,
                'is_available' => true,
                'label' => __('menu.guest.available_always'),
                'detail' => __('menu.guest.availability_schedule_missing'),
                'tone' => 'muted',
                'next_available_at' => null,
                'available_until' => null,
                'timezone' => $timezone,
            ];
        }

        $availableInterval = $this->currentAvailableInterval($schedules, $currentTime);

        if ($availableInterval !== null) {
            return [
                'is_configured' => true,
                'is_available' => true,
                'label' => __('menu.guest.available_now'),
                'detail' => __('menu.guest.available_until', ['time' => $availableInterval['ends_at']]),
                'tone' => 'success',
                'next_available_at' => null,
                'available_until' => $availableInterval['ends_at'],
                'timezone' => $timezone,
            ];
        }

        $nextAvailable = $this->nextAvailableInterval($schedules, $currentTime);

        return [
            'is_configured' => true,
            'is_available' => false,
            'label' => __('menu.guest.unavailable'),
            'detail' => $nextAvailable === null
                ? __('menu.guest.schedule_unknown')
                : __('menu.guest.available_from', ['time' => $nextAvailable['label']]),
            'tone' => 'warning',
            'next_available_at' => $nextAvailable['time'] ?? null,
            'available_until' => null,
            'timezone' => $timezone,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function dayLabels(): array
    {
        return GetBranchOpeningStatusAction::dayLabels();
    }

    /**
     * @param  Collection<int, MenuAvailabilitySchedule>  $schedules
     * @return array{ends_at: string}|null
     */
    private function currentAvailableInterval(Collection $schedules, CarbonInterface $now): ?array
    {
        foreach ([-1, 0] as $offset) {
            $date = $now->copy()->startOfDay()->addDays($offset);
            $dayOfWeek = (int) $date->isoWeekday();

            foreach ($this->intervalsForDay($schedules, $dayOfWeek) as $schedule) {
                $start = $this->dateAtTime($date, (string) $schedule->starts_at);
                $end = $this->dateAtTime($date, (string) $schedule->ends_at);

                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }

                if ($now->greaterThanOrEqualTo($start) && $now->lessThan($end)) {
                    return ['ends_at' => $end->format('H:i')];
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MenuAvailabilitySchedule>  $schedules
     * @return array{time: string, label: string}|null
     */
    private function nextAvailableInterval(Collection $schedules, CarbonInterface $now): ?array
    {
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $now->copy()->startOfDay()->addDays($offset);
            $dayOfWeek = (int) $date->isoWeekday();

            foreach ($this->intervalsForDay($schedules, $dayOfWeek) as $schedule) {
                $start = $this->dateAtTime($date, (string) $schedule->starts_at);

                if ($start->greaterThan($now)) {
                    return [
                        'time' => $start->toIso8601String(),
                        'label' => $offset === 0
                            ? $start->format('H:i')
                            : $this->shortDayLabel($dayOfWeek).' '.$start->format('H:i'),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MenuAvailabilitySchedule>  $schedules
     * @return Collection<int, MenuAvailabilitySchedule>
     */
    private function intervalsForDay(Collection $schedules, int $dayOfWeek): Collection
    {
        return $schedules
            ->filter(fn (MenuAvailabilitySchedule $schedule): bool => $schedule->day_of_week === $dayOfWeek
                && is_string($schedule->starts_at)
                && is_string($schedule->ends_at))
            ->sortBy([
                ['starts_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    private function dateAtTime(CarbonInterface $date, string $time): CarbonInterface
    {
        [$hours, $minutes] = array_pad(explode(':', substr($time, 0, 5)), 2, 0);

        return $date->copy()->setTime((int) $hours, (int) $minutes);
    }

    private function branchFor(Menu $menu): ?Branch
    {
        $branch = $menu->relationLoaded('branch') ? $menu->branch : null;

        if ($branch instanceof Branch) {
            return $branch;
        }

        return $menu->branch()
            ->select(['id', 'timezone'])
            ->first();
    }

    /**
     * @return Collection<int, MenuAvailabilitySchedule>
     */
    private function schedulesFor(Menu $menu): Collection
    {
        if ($menu->relationLoaded('availabilitySchedules')) {
            return $menu->availabilitySchedules;
        }

        return $menu->availabilitySchedules()
            ->select(['id', 'menu_id', 'day_of_week', 'starts_at', 'ends_at'])
            ->get();
    }

    private function timezoneFor(?Branch $branch): string
    {
        $timezone = $branch?->getAttribute('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : config('app.timezone', 'UTC');
    }

    private function shortDayLabel(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1 => __('menu.guest.days.mon'),
            2 => __('menu.guest.days.tue'),
            3 => __('menu.guest.days.wed'),
            4 => __('menu.guest.days.thu'),
            5 => __('menu.guest.days.fri'),
            6 => __('menu.guest.days.sat'),
            7 => __('menu.guest.days.sun'),
            default => '',
        };
    }
}
