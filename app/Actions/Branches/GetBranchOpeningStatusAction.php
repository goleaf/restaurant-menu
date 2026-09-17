<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Services\Availability\OpeningIntervalEvaluator;
use App\Support\LocalizedDateFormatter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** @phpstan-import-type Layer from OpeningIntervalEvaluator */
class GetBranchOpeningStatusAction
{
    public function __construct(private readonly OpeningIntervalEvaluator $evaluator) {}

    /** @return array{is_configured: bool, is_open: bool, can_accept_orders: bool, label: string, detail: string, tone: string, next_opens_at: string|null, closes_at: string|null, timezone: string, reason_codes: list<string>, evaluated_at: string, next_change_at: string|null, next_orderable_at: string|null} */
    public function handle(Branch $branch, ?CarbonInterface $now = null): array
    {
        $instant = $now === null ? CarbonImmutable::now() : CarbonImmutable::instance($now);
        $timezone = $branch->timezone ?: config('app.timezone', 'UTC');
        $layer = $this->temporalLayer($branch);
        $until = $branch->temporaryClosedUntilForBranch();
        $until = $until === null ? null : CarbonImmutable::instance($until);
        $paused = (bool) $branch->is_temporarily_closed && ($until === null || $until->greaterThan($instant));
        $result = $this->evaluator->evaluate([$layer], $timezone, $instant, $paused ? $until : null, $paused && $until === null);
        $configured = ! $layer['empty_allows'] || $layer['exceptions'] !== [];
        $allowed = $result['allows_now'];
        $next = $allowed ? null : $result['next_orderable_at'];
        $closes = $result['current_closes_at'];
        $reason = $paused ? 'branch_paused' : (! $allowed ? 'branch_schedule_closed' : null);
        if ($paused) {
            $label = __('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt');
            $detail = trim((string) $branch->temporary_closed_reason);
            $detail .= ($detail === '' ? '' : '. ').($until === null
                ? __('ui.actions.branches.getbranchopeningstatusaction.otkroemsia_pozze')
                : __('ui.actions.branches.getbranchopeningstatusaction.zakryto_do', ['time' => LocalizedDateFormatter::dateTime($until->setTimezone($timezone))]));
        } elseif (! $configured) {
            $label = __('ui.actions.branches.getbranchopeningstatusaction.casy_raboty_ne_ukazany');
            $detail = __('ui.actions.branches.getbranchopeningstatusaction.mozno_smotret_meniu_zakaz');
        } elseif ($allowed) {
            $label = __('ui.actions.branches.getbranchopeningstatusaction.seicas_otkryto');
            $detail = $closes === null ? __('availability.open_without_deadline') : __('ui.actions.branches.getbranchopeningstatusaction.otkryto_do', ['time' => LocalizedDateFormatter::time($closes)]);
        } else {
            $label = __('ui.actions.branches.getbranchopeningstatusaction.seicas_zakryto');
            $detail = $next === null ? __('ui.actions.branches.getbranchopeningstatusaction.segodnia_zakryto')
                : __('ui.actions.branches.getbranchopeningstatusaction.otkroetsia_v', ['time' => $this->openingLabel($next, $instant->setTimezone($timezone))]);
        }

        return ['is_configured' => $paused || $configured, 'is_open' => ! $paused && $configured && $allowed,
            'can_accept_orders' => $allowed, 'label' => $label, 'detail' => $detail,
            'tone' => $paused ? 'danger' : (! $configured ? 'muted' : ($allowed ? 'success' : 'warning')),
            'next_opens_at' => $next?->toIso8601String(), 'closes_at' => $closes === null ? null : LocalizedDateFormatter::time($closes),
            'timezone' => $timezone, 'reason_codes' => $reason === null ? [] : [$reason],
            'evaluated_at' => $instant->toIso8601String(), 'next_change_at' => $result['next_change_at']?->toIso8601String(),
            'next_orderable_at' => $result['next_orderable_at']?->toIso8601String()];
    }

    /** @return Layer */
    public function temporalLayer(Branch $branch): array
    {
        $branch->loadMissing([
            'openingHours:id,branch_id,day_of_week,is_closed,opens_at,closes_at,sort_order',
            'scheduleExceptions:id,branch_id,local_date,is_closed,intervals',
        ]);
        $weekly = [];
        foreach ($branch->openingHours as $hour) {
            if (! $hour->is_closed && is_string($hour->opens_at) && is_string($hour->closes_at)) {
                $weekly[] = ['day_of_week' => $hour->day_of_week, 'opens_at' => $hour->opens_at, 'closes_at' => $hour->closes_at];
            }
        }
        $exceptions = [];
        foreach ($branch->scheduleExceptions as $exception) {
            $exceptions[] = ['local_date' => $exception->local_date, 'is_closed' => $exception->is_closed, 'intervals' => $exception->intervals ?? []];
        }

        return ['weekly' => $weekly, 'exceptions' => $exceptions, 'empty_allows' => $branch->openingHours->isEmpty()];
    }

    private function openingLabel(CarbonImmutable $next, CarbonImmutable $instant): string
    {
        if ($next->isSameDay($instant)) {
            return LocalizedDateFormatter::time($next);
        }
        $key = ['pn', 'vt', 'sr', 'ct', 'pt', 'sb', 'vs'][$next->isoWeekday() - 1];

        $translationKey = 'ui.actions.branches.getbranchopeningstatusaction.'.$key;

        return __($translationKey).' '.LocalizedDateFormatter::time($next);
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
}
