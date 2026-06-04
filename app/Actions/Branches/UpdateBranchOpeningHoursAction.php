<?php

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\BranchOpeningHour;
use Illuminate\Support\Facades\DB;

class UpdateBranchOpeningHoursAction
{
    /**
     * @param  list<array{day_of_week: int, is_closed: bool, intervals: list<array{opens_at: string, closes_at: string}>}>  $weeklyHours
     */
    public function handle(Branch $branch, array $weeklyHours, bool $isConfigured = true): void
    {
        DB::transaction(function () use ($branch, $weeklyHours, $isConfigured): void {
            $branch->openingHours()->delete();

            if (! $isConfigured) {
                return;
            }

            foreach ($weeklyHours as $day) {
                $dayOfWeek = (int) $day['day_of_week'];

                if ($dayOfWeek < 1 || $dayOfWeek > 7) {
                    continue;
                }

                if ((bool) $day['is_closed']) {
                    $this->createClosedDay($branch, $dayOfWeek);

                    continue;
                }

                foreach ($day['intervals'] as $index => $interval) {
                    $branch->openingHours()->create([
                        'day_of_week' => $dayOfWeek,
                        'is_closed' => false,
                        'opens_at' => $this->normalizeTime($interval['opens_at']),
                        'closes_at' => $this->normalizeTime($interval['closes_at']),
                        'sort_order' => ($index + 1) * 10,
                    ]);
                }
            }
        });
    }

    private function createClosedDay(Branch $branch, int $dayOfWeek): BranchOpeningHour
    {
        return $branch->openingHours()->create([
            'day_of_week' => $dayOfWeek,
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
            'sort_order' => 0,
        ]);
    }

    private function normalizeTime(string $time): string
    {
        return substr($time, 0, 5);
    }
}
