<?php

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\BranchOpeningHour;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetBranchOpeningStatusAction
{
    /**
     * @return array{is_configured: bool, is_open: bool, can_accept_orders: bool, label: string, detail: string, tone: string, next_opens_at: string|null, closes_at: string|null, timezone: string}
     */
    public function handle(Branch $branch, ?CarbonInterface $now = null): array
    {
        $timezone = $this->timezoneFor($branch);
        $currentTime = $now instanceof CarbonInterface
            ? Carbon::instance($now->toDateTime())->setTimezone($timezone)
            : now($timezone);
        $temporaryClosedStatus = $this->temporaryClosedStatus($branch, $currentTime, $timezone);

        if ($temporaryClosedStatus !== null) {
            return $temporaryClosedStatus;
        }

        $openingHours = $this->openingHoursFor($branch);

        if ($openingHours->isEmpty()) {
            return [
                'is_configured' => false,
                'is_open' => false,
                'can_accept_orders' => true,
                'label' => __('ui.actions.branches.getbranchopeningstatusaction.casy_raboty_ne_ukazany'),
                'detail' => __('ui.actions.branches.getbranchopeningstatusaction.mozno_smotret_meniu_zakaz'),
                'tone' => 'muted',
                'next_opens_at' => null,
                'closes_at' => null,
                'timezone' => $timezone,
            ];
        }

        $openInterval = $this->currentOpenInterval($openingHours, $currentTime);

        if ($openInterval !== null) {
            return [
                'is_configured' => true,
                'is_open' => true,
                'can_accept_orders' => true,
                'label' => __('ui.actions.branches.getbranchopeningstatusaction.seicas_otkryto'),
                'detail' => __('ui.actions.branches.getbranchopeningstatusaction.otkryto_do', ['time' => $openInterval['closes_at']]),
                'tone' => 'success',
                'next_opens_at' => null,
                'closes_at' => $openInterval['closes_at'],
                'timezone' => $timezone,
            ];
        }

        $nextOpening = $this->nextOpening($openingHours, $currentTime);

        return [
            'is_configured' => true,
            'is_open' => false,
            'can_accept_orders' => false,
            'label' => __('ui.actions.branches.getbranchopeningstatusaction.seicas_zakryto'),
            'detail' => $nextOpening === null
                ? __('ui.actions.branches.getbranchopeningstatusaction.segodnia_zakryto')
                : __('ui.actions.branches.getbranchopeningstatusaction.otkroetsia_v', ['time' => $nextOpening['label']]),
            'tone' => 'warning',
            'next_opens_at' => $nextOpening['time'] ?? null,
            'closes_at' => null,
            'timezone' => $timezone,
        ];
    }

    /**
     * @return array{is_configured: bool, is_open: bool, can_accept_orders: bool, label: string, detail: string, tone: string, next_opens_at: string|null, closes_at: string|null, timezone: string}|null
     */
    private function temporaryClosedStatus(Branch $branch, CarbonInterface $now, string $timezone): ?array
    {
        if (! (bool) $branch->getAttribute('is_temporarily_closed')) {
            return null;
        }

        $closedUntil = $branch->temporaryClosedUntilForBranch();

        if ($closedUntil instanceof CarbonInterface) {
            if ($closedUntil->lessThanOrEqualTo($now)) {
                return null;
            }
        }

        $reason = str((string) $branch->getAttribute('temporary_closed_reason'))->squish()->toString();
        $detailParts = [];

        if ($reason !== '') {
            $detailParts[] = $reason;
        }

        if ($closedUntil instanceof CarbonInterface) {
            $detailParts[] = __('ui.actions.branches.getbranchopeningstatusaction.zakryto_do', [
                'time' => $closedUntil->isSameDay($now)
                    ? $closedUntil->format('H:i')
                    : $closedUntil->format('d.m H:i'),
            ]);
        } else {
            $detailParts[] = __('ui.actions.branches.getbranchopeningstatusaction.otkroemsia_pozze');
        }

        return [
            'is_configured' => true,
            'is_open' => false,
            'can_accept_orders' => false,
            'label' => __('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt'),
            'detail' => implode('. ', $detailParts),
            'tone' => 'danger',
            'next_opens_at' => $closedUntil instanceof CarbonInterface ? $closedUntil->toIso8601String() : null,
            'closes_at' => null,
            'timezone' => $timezone,
        ];
    }

    public static function dayLabels(): array
    {
        return [
            1 => __('ui.actions.branches.getbranchopeningstatusaction.ponedelnik'),
            2 => __('ui.actions.branches.getbranchopeningstatusaction.vtornik'),
            3 => __('ui.actions.branches.getbranchopeningstatusaction.sreda'),
            4 => __('ui.actions.branches.getbranchopeningstatusaction.cetverg'),
            5 => __('ui.actions.branches.getbranchopeningstatusaction.piatnica'),
            6 => __('ui.actions.branches.getbranchopeningstatusaction.subbota'),
            7 => __('ui.actions.branches.getbranchopeningstatusaction.voskresene'),
        ];
    }

    /**
     * @return Collection<int, BranchOpeningHour>
     */
    private function openingHoursFor(Branch $branch): Collection
    {
        return $branch->openingHours()
            ->select([
                'id',
                'branch_id',
                'day_of_week',
                'is_closed',
                'opens_at',
                'closes_at',
                'sort_order',
            ])
            ->get();
    }

    /**
     * @param  Collection<int, BranchOpeningHour>  $openingHours
     * @return array{closes_at: string}|null
     */
    private function currentOpenInterval(Collection $openingHours, CarbonInterface $now): ?array
    {
        foreach ([-1, 0] as $offset) {
            $date = $now->copy()->startOfDay()->addDays($offset);
            $dayOfWeek = (int) $date->isoWeekday();

            foreach ($this->openIntervalsForDay($openingHours, $dayOfWeek) as $openingHour) {
                $start = $this->dateAtTime($date, (string) $openingHour->opens_at);
                $end = $this->dateAtTime($date, (string) $openingHour->closes_at);

                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }

                if ($now->greaterThanOrEqualTo($start) && $now->lessThan($end)) {
                    return ['closes_at' => $end->format('H:i')];
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, BranchOpeningHour>  $openingHours
     * @return array{time: string, label: string}|null
     */
    private function nextOpening(Collection $openingHours, CarbonInterface $now): ?array
    {
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $now->copy()->startOfDay()->addDays($offset);
            $dayOfWeek = (int) $date->isoWeekday();

            foreach ($this->openIntervalsForDay($openingHours, $dayOfWeek) as $openingHour) {
                $start = $this->dateAtTime($date, (string) $openingHour->opens_at);

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
     * @param  Collection<int, BranchOpeningHour>  $openingHours
     * @return Collection<int, BranchOpeningHour>
     */
    private function openIntervalsForDay(Collection $openingHours, int $dayOfWeek): Collection
    {
        return $openingHours
            ->filter(fn (BranchOpeningHour $openingHour): bool => $openingHour->day_of_week === $dayOfWeek
                && ! $openingHour->is_closed
                && is_string($openingHour->opens_at)
                && is_string($openingHour->closes_at))
            ->sortBy([
                ['sort_order', 'asc'],
                ['opens_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    private function dateAtTime(CarbonInterface $date, string $time): CarbonInterface
    {
        [$hours, $minutes] = array_pad(explode(':', substr($time, 0, 5)), 2, 0);

        return $date->copy()->setTime((int) $hours, (int) $minutes);
    }

    private function timezoneFor(Branch $branch): string
    {
        $timezone = $branch->getAttribute('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : config('app.timezone', 'UTC');
    }

    private function shortDayLabel(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1 => __('ui.actions.branches.getbranchopeningstatusaction.pn'),
            2 => __('ui.actions.branches.getbranchopeningstatusaction.vt'),
            3 => __('ui.actions.branches.getbranchopeningstatusaction.sr'),
            4 => __('ui.actions.branches.getbranchopeningstatusaction.ct'),
            5 => __('ui.actions.branches.getbranchopeningstatusaction.pt'),
            6 => __('ui.actions.branches.getbranchopeningstatusaction.sb'),
            7 => __('ui.actions.branches.getbranchopeningstatusaction.vs'),
            default => '',
        };
    }
}
