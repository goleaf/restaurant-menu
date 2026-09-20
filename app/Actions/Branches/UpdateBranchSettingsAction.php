<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\BranchSetting;

class UpdateBranchSettingsAction
{
    /**
     * @param  array{
     *     require_waiter_confirmation_for_orders: bool,
     *     allow_guest_created_sessions: bool,
     *     allow_waiter_opened_sessions: bool,
     *     allow_guest_invite_links: bool,
     *     guest_join_requires_approval: bool,
     *     polling_interval_seconds: int,
     *     inactivity_warning_minutes: int,
     *     pending_session_expire_minutes: int,
     *     default_language: string,
     *     default_currency: string,
     *     service_charge_enabled: bool,
     *     service_charge_percent?: string,
     *     tips_enabled: bool,
     *     order_flow_mode: string,
     *     service_modes?: list<string>
     * }  $data
     */
    public function handle(BranchSetting $settings, array $data): BranchSetting
    {
        throw new \LogicException('Use a versioned settings group operation.');
    }
}
