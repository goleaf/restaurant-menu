<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Models\Branch;
use App\Models\Menu;
use App\Services\Availability\OpeningIntervalEvaluator;
use App\Support\DisplayPreferences;
use App\Support\LocalizedDateFormatter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GetMenuAvailabilityStatusAction
{
    public function __construct(private readonly OpeningIntervalEvaluator $evaluator, private readonly GetBranchOpeningStatusAction $branchOpening) {}

    /** @return array{is_configured: bool, is_available: bool, label: string, detail: string, tone: string, next_available_at: string|null, available_until: string|null, timezone: string, reason_codes: list<string>, evaluated_at: string, next_change_at: string|null, next_orderable_at: string|null} */
    public function handle(Menu $menu, ?CarbonInterface $now = null, ?DisplayPreferences $preferences = null): array
    {
        $instant = $now === null ? CarbonImmutable::now() : CarbonImmutable::instance($now);
        $branch = $this->branchFor($menu);
        $timezone = $branch->timezone ?: config('app.timezone', 'UTC');
        $branchStatus = $this->branchOpening->handle($branch, $instant, $preferences);
        $menu->loadMissing('availabilitySchedules:id,menu_id,day_of_week,starts_at,ends_at');
        $scheduleClosed = array_key_exists('schedule_is_closed', $menu->getAttributes())
            ? (bool) $menu->schedule_is_closed
            : (bool) Menu::query()->whereKey($menu->id)->value('schedule_is_closed');
        $weekly = [];
        foreach ($menu->availabilitySchedules as $schedule) {
            $weekly[] = ['day_of_week' => $schedule->day_of_week, 'opens_at' => $schedule->starts_at, 'closes_at' => $schedule->ends_at];
        }
        $menuLayer = ['weekly' => $scheduleClosed ? [] : $weekly, 'exceptions' => [], 'empty_allows' => ! $scheduleClosed && $weekly === []];
        $until = $branch->temporaryClosedUntilForBranch();
        $until = $until === null ? null : CarbonImmutable::instance($until);
        $paused = (bool) $branch->is_temporarily_closed && ($until === null || $until->greaterThan($instant));
        $result = $this->evaluator->evaluate([$this->branchOpening->temporalLayer($branch), $menuLayer], $timezone, $instant, $paused ? $until : null, $paused && $until === null);
        $own = $this->evaluator->evaluate([$menuLayer], $timezone, $instant);
        $allowed = $result['allows_now'];
        $configured = $scheduleClosed || $weekly !== [];
        $next = $allowed ? null : $result['next_orderable_at'];
        $closes = $result['current_closes_at'];
        $reasons = $branchStatus['can_accept_orders'] ? [] : $branchStatus['reason_codes'];
        if (! $own['allows_now']) {
            $reasons[] = 'menu_schedule_closed';
        }
        if (! $allowed) {
            $label = __('menu.guest.unavailable');
            $detail = $next === null ? __('menu.guest.schedule_unknown')
                : __('menu.guest.available_from', ['time' => $this->openingLabel($next, $instant->setTimezone($timezone), $preferences)]);
        } elseif (! $configured && ! $branchStatus['is_configured']) {
            $label = __('menu.guest.available_always');
            $detail = __('menu.guest.availability_schedule_missing');
        } else {
            $label = __('menu.guest.available_now');
            $detail = $closes === null ? __('availability.open_without_deadline') : __('menu.guest.available_until', ['time' => LocalizedDateFormatter::time($closes, $preferences)]);
        }

        return ['is_configured' => $configured, 'is_available' => $allowed, 'label' => $label, 'detail' => $detail,
            'tone' => $allowed ? ($configured ? 'success' : 'muted') : 'warning',
            'next_available_at' => $next?->toIso8601String(), 'available_until' => $closes === null ? null : LocalizedDateFormatter::time($closes, $preferences),
            'timezone' => $timezone, 'reason_codes' => $reasons, 'evaluated_at' => $instant->toIso8601String(),
            'next_change_at' => $result['next_change_at']?->toIso8601String(), 'next_orderable_at' => $result['next_orderable_at']?->toIso8601String()];
    }

    /** @return array<int, string> */
    public static function dayLabels(): array
    {
        return GetBranchOpeningStatusAction::dayLabels();
    }

    private function branchFor(Menu $menu): Branch
    {
        $branch = $menu->relationLoaded('branch') ? $menu->branch : null;
        if ($branch instanceof Branch && array_key_exists('is_temporarily_closed', $branch->getAttributes()) && array_key_exists('temporary_closed_until', $branch->getAttributes())) {
            return $branch;
        }
        $branch = $menu->branch()->select(['id', 'timezone', 'is_temporarily_closed', 'temporary_closed_reason', 'temporary_closed_until'])->firstOrFail();
        $menu->setRelation('branch', $branch);

        return $branch;
    }

    private function openingLabel(CarbonImmutable $next, CarbonImmutable $instant, ?DisplayPreferences $preferences): string
    {
        if ($next->isSameDay($instant)) {
            return LocalizedDateFormatter::time($next, $preferences);
        }
        $key = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][$next->isoWeekday() - 1];

        $translationKey = 'menu.guest.days.'.$key;

        return __($translationKey).' '.LocalizedDateFormatter::time($next, $preferences);
    }
}
