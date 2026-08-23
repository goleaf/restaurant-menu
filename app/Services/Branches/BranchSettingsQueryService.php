<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\BranchSetting;

final class BranchSettingsQueryService
{
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
}
