<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionStatus;
use App\Models\BranchSetting;
use App\Models\TableSession;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BuildTableSessionInactivityStateAction
{
    /**
     * @return array{
     *     should_warn: bool,
     *     should_expire_pending: bool,
     *     minutes_inactive: int,
     *     warning_minutes: int,
     *     pending_expire_minutes: int,
     *     last_activity_at: string|null
     * }
     */
    public function handle(TableSession $tableSession, ?BranchSetting $settings = null): array
    {
        $settings ??= $this->settingsFor($tableSession);
        $defaults = BranchSetting::defaults();
        $warningMinutes = $this->positiveMinutes(
            $settings?->inactivity_warning_minutes,
            $defaults['inactivity_warning_minutes'],
        );
        $pendingExpireMinutes = $this->positiveMinutes(
            $settings?->pending_session_expire_minutes,
            $defaults['pending_session_expire_minutes'],
        );
        $lastActivityAt = $this->lastActivityAt($tableSession);
        $minutesInactive = $this->minutesInactive($tableSession, $lastActivityAt);
        $status = $this->status($tableSession);

        return [
            'should_warn' => $status === TableSessionStatus::Active && $minutesInactive >= $warningMinutes,
            'should_expire_pending' => $status === TableSessionStatus::Pending && $minutesInactive >= $pendingExpireMinutes,
            'minutes_inactive' => $minutesInactive,
            'warning_minutes' => $warningMinutes,
            'pending_expire_minutes' => $pendingExpireMinutes,
            'last_activity_at' => $lastActivityAt?->toISOString(),
        ];
    }

    private function settingsFor(TableSession $tableSession): ?BranchSetting
    {
        if ($tableSession->relationLoaded('branch') && $tableSession->branch->relationLoaded('settings')) {
            $settings = $tableSession->branch->getRelation('settings');

            return $settings instanceof BranchSetting ? $settings : null;
        }

        $tableSession->loadMissing([
            'branch' => fn ($query) => $query->select(['id', 'timezone']),
            'branch.settings' => fn ($query) => $query->select([
                'id',
                'branch_id',
                'inactivity_warning_minutes',
                'pending_session_expire_minutes',
            ]),
        ]);

        $settings = $tableSession->branch->getRelation('settings');

        return $settings instanceof BranchSetting ? $settings : null;
    }

    private function lastActivityAt(TableSession $tableSession): ?CarbonInterface
    {
        return collect([
            $tableSession->updated_at,
            $tableSession->started_at,
            $tableSession->created_at,
            ...array_map(function (string $attribute) use ($tableSession): ?CarbonInterface {
                $value = $tableSession->getAttributes()[$attribute] ?? null;

                return is_string($value) ? CarbonImmutable::parse($value, 'UTC') : null;
            }, [
                'guests_max_joined_at', 'guests_max_left_at', 'guests_max_ready_at',
                'join_requests_max_created_at', 'waiter_calls_max_requested_at', 'waiter_calls_max_handled_at',
            ]),
        ])
            ->filter(fn (mixed $value): bool => $value instanceof CarbonInterface)
            ->sortDesc()
            ->first();
    }

    private function minutesInactive(TableSession $tableSession, ?CarbonInterface $lastActivityAt): int
    {
        if (! $lastActivityAt instanceof CarbonInterface) {
            return 0;
        }

        $timezone = $tableSession->branch->timezone ?: config('app.timezone', 'UTC');
        $now = now($timezone);

        return max(0, (int) floor($lastActivityAt->copy()->setTimezone($timezone)->diffInMinutes($now)));
    }

    private function positiveMinutes(mixed $value, int $default): int
    {
        $minutes = (int) $value;

        return $minutes > 0 ? $minutes : $default;
    }

    private function status(TableSession $tableSession): TableSessionStatus
    {
        return $tableSession->status;
    }
}
