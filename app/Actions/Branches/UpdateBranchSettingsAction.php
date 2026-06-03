<?php

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
     *     default_language: string,
     *     default_currency: string,
     *     service_charge_enabled: bool,
     *     tips_enabled: bool,
     *     order_flow_mode: string
     * }  $data
     */
    public function handle(BranchSetting $settings, array $data): BranchSetting
    {
        $settings->fill($data);
        $settings->save();

        return $settings;
    }
}
