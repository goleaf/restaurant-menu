<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use Carbon\CarbonInterface;

final class BuildDepartmentTicketDelayTimerAction
{
    private const int ATTENTION_AFTER_SECONDS = 600;

    private const int DELAYED_AFTER_SECONDS = 900;

    /**
     * @return array{
     *     elapsed_seconds: int,
     *     elapsed_label: string,
     *     delay_state: 'on-track'|'attention'|'delayed',
     *     delay_status_label: string,
     *     delay_label: string|null,
     *     delay_description: string|null,
     *     attention_after_seconds: int,
     *     delayed_after_seconds: int
     * }
     */
    public function handle(?CarbonInterface $startedAt, ?CarbonInterface $referenceTime = null): array
    {
        $elapsedSeconds = $this->elapsedSeconds($startedAt, $referenceTime ?? now());
        $state = $this->state($elapsedSeconds);
        $delaySeconds = max(0, $elapsedSeconds - self::DELAYED_AFTER_SECONDS);
        $delayLabel = $state === 'delayed' ? $this->formatDuration($delaySeconds) : null;

        return [
            'elapsed_seconds' => $elapsedSeconds,
            'elapsed_label' => $this->formatDuration($elapsedSeconds),
            'delay_state' => $state,
            'delay_status_label' => $this->statusLabel($state),
            'delay_label' => $delayLabel,
            'delay_description' => $delayLabel === null
                ? null
                : (string) __('ui.departments.dashboard.delay_by', ['time' => $delayLabel]),
            'attention_after_seconds' => self::ATTENTION_AFTER_SECONDS,
            'delayed_after_seconds' => self::DELAYED_AFTER_SECONDS,
        ];
    }

    private function elapsedSeconds(?CarbonInterface $startedAt, CarbonInterface $referenceTime): int
    {
        if (! $startedAt instanceof CarbonInterface || $startedAt->isAfter($referenceTime)) {
            return 0;
        }

        return (int) $startedAt->diffInSeconds($referenceTime);
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return $hours.':'.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT);
        }

        return str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return 'on-track'|'attention'|'delayed'
     */
    private function state(int $elapsedSeconds): string
    {
        if ($elapsedSeconds >= self::DELAYED_AFTER_SECONDS) {
            return 'delayed';
        }

        if ($elapsedSeconds >= self::ATTENTION_AFTER_SECONDS) {
            return 'attention';
        }

        return 'on-track';
    }

    /**
     * @param  'on-track'|'attention'|'delayed'  $state
     */
    private function statusLabel(string $state): string
    {
        return match ($state) {
            'delayed' => (string) __('ui.departments.dashboard.delay_status.delayed'),
            'attention' => (string) __('ui.departments.dashboard.delay_status.attention'),
            'on-track' => (string) __('ui.departments.dashboard.delay_status.on_track'),
        };
    }
}
