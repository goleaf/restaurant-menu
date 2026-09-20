<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Models\Branch;
use App\Models\BranchSetting;

final class BranchSettingsQueryService
{
    public function effective(Branch $branch): BranchSetting
    {
        $settings = BranchSetting::query()->where('branch_id', $branch->id)->first();
        if ($settings instanceof BranchSetting) {
            return $settings;
        }

        return (new BranchSetting)->forceFill(['branch_id' => $branch->id, ...BranchSetting::defaults($branch)]);
    }

    public function find(Branch $branch, int $settingsId): BranchSetting
    {
        return BranchSetting::query()
            ->select([
                'id',
                'branch_id',
                'require_waiter_confirmation_for_orders',
                'allow_guest_created_sessions',
                'allow_waiter_opened_sessions',
                'allow_guest_invite_links',
                'guest_join_requires_approval',
                'polling_interval_seconds',
                'inactivity_warning_minutes',
                'pending_session_expire_minutes',
                'default_language',
                'default_currency',
                'service_charge_enabled',
                'service_charge_basis_points',
                'tips_enabled',
                'order_flow_mode',
                'service_modes',
                'created_at',
                'updated_at',
            ])
            ->whereKey($settingsId)
            ->where('branch_id', $branch->id)
            ->firstOrFail();
    }

    /** @return array{configured: bool, days: list<array<string, mixed>>} */
    public function openingHours(Branch $branch): array
    {
        $openingHours = $branch->openingHours()
            ->select([
                'id',
                'branch_id',
                'day_of_week',
                'is_closed',
                'opens_at',
                'closes_at',
                'sort_order',
            ])
            ->get()
            ->groupBy('day_of_week');

        $configured = $openingHours->isNotEmpty();
        $days = collect(GetBranchOpeningStatusAction::dayLabels())
            ->map(function (string $label, int $dayOfWeek) use ($openingHours, $configured): array {
                $dayRows = $openingHours->get($dayOfWeek, collect());
                $intervals = $dayRows
                    ->filter(fn ($openingHour): bool => ! $openingHour->is_closed)
                    ->map(fn ($openingHour): array => [
                        'opens_at' => substr((string) $openingHour->opens_at, 0, 5),
                        'closes_at' => substr((string) $openingHour->closes_at, 0, 5),
                    ])
                    ->values()
                    ->all();

                return [
                    'day_of_week' => $dayOfWeek,
                    'label' => $label,
                    'is_closed' => $configured && $intervals === [],
                    'intervals' => $intervals !== [] ? $intervals : [
                        [
                            'opens_at' => '10:00',
                            'closes_at' => '22:00',
                        ],
                    ],
                ];
            })
            ->values()
            ->all();

        return ['configured' => $configured, 'days' => $days];
    }
}
