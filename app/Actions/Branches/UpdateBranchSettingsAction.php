<?php

namespace App\Actions\Branches;

use App\Enums\SupportedCurrency;
use App\Models\BranchSetting;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($settings, $data): BranchSetting {
            $data['default_currency'] = SupportedCurrency::normalize($data['default_currency'] ?? null);

            $settings->fill($data);
            $settings->save();

            $settings->branch()
                ->select(['id', 'currency'])
                ->update(['currency' => $data['default_currency']]);

            GetBranchPollingIntervalAction::forgetForBranch((int) $settings->branch_id);

            return $settings->refresh();
        });
    }
}
