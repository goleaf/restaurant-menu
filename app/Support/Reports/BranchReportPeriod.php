<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class BranchReportPeriod
{
    /**
     * @param  array<int, array{timezone: string, currency: string, date_from: string, date_to: string, start: string, end: string}>  $ranges
     */
    private function __construct(public string $preset, public array $ranges) {}

    /**
     * @param  Collection<int, Branch>  $branches
     */
    public static function fromSelection(
        Collection $branches,
        string $preset = 'today',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?CarbonImmutable $now = null,
    ): self {
        if (! in_array($preset, ['today', 'yesterday', 'last7', 'custom'], true)) {
            throw ValidationException::withMessages(['period' => __('reports.errors.period')]);
        }

        if ($preset === 'custom') {
            new CalendarDateRange($dateFrom ?? '', $dateTo ?? '', 'UTC');
        }

        $now ??= CarbonImmutable::now();
        $ranges = [];

        foreach ($branches->sortBy('id') as $branch) {
            $timezone = $branch->timezone;
            $today = $now->setTimezone($timezone)->startOfDay();
            $start = match ($preset) {
                'custom' => CarbonImmutable::parse($dateFrom, $timezone)->startOfDay(),
                'yesterday' => $today->subDay(),
                'last7' => $today->subDays(6),
                default => $today,
            };
            $end = match ($preset) {
                'custom' => CarbonImmutable::parse($dateTo, $timezone)->startOfDay()->addDay(),
                'yesterday' => $today,
                default => $today->addDay(),
            };
            $ranges[$branch->id] = [
                'timezone' => $timezone,
                'currency' => $branch->currency,
                'date_from' => $start->toDateString(),
                'date_to' => $end->subDay()->toDateString(),
                'start' => $start->setTimezone('UTC')->toDateTimeString(),
                'end' => $end->setTimezone('UTC')->toDateTimeString(),
            ];
        }

        return new self($preset, $ranges);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->preset, $this->ranges], JSON_THROW_ON_ERROR));
    }

    public function label(): string
    {
        return collect($this->ranges)->map(fn (array $range): string => $range['date_from'] === $range['date_to']
            ? $range['date_from']
            : $range['date_from'].' – '.$range['date_to'])->unique()->implode(' / ');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, string $timestampColumn): Builder
    {
        if ($this->ranges === []) {
            return $query->whereIn($query->qualifyColumn('branch_id'), []);
        }

        return $query->where(function (Builder $query) use ($timestampColumn): void {
            foreach ($this->ranges as $branchId => $range) {
                $query->orWhere(fn (Builder $branchQuery): Builder => $branchQuery
                    ->where($branchQuery->qualifyColumn('branch_id'), $branchId)
                    ->where($branchQuery->qualifyColumn($timestampColumn), '>=', $range['start'])
                    ->where($branchQuery->qualifyColumn($timestampColumn), '<', $range['end']));
            }
        });
    }

}
