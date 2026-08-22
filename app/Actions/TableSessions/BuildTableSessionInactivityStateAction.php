<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionStatus;
use App\Models\BranchSetting;
use App\Models\TableSession;
use Carbon\CarbonInterface;

class BuildTableSessionInactivityStateAction
{
    public const DEFAULT_INACTIVITY_WARNING_MINUTES = 45;

    public const DEFAULT_PENDING_SESSION_EXPIRE_MINUTES = 30;

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
        $warningMinutes = $this->positiveMinutes(
            $settings?->inactivity_warning_minutes,
            self::DEFAULT_INACTIVITY_WARNING_MINUTES,
        );
        $pendingExpireMinutes = $this->positiveMinutes(
            $settings?->pending_session_expire_minutes,
            self::DEFAULT_PENDING_SESSION_EXPIRE_MINUTES,
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
