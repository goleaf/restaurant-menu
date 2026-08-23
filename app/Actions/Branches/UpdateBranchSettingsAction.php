<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Enums\BranchServiceMode;
use App\Enums\SupportedCurrency;
use App\Models\BranchSetting;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\DB;

class UpdateBranchSettingsAction
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

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
        return DB::transaction(function () use ($settings, $data): BranchSetting {
            $data['default_currency'] = SupportedCurrency::normalize($data['default_currency']);
            $data['service_charge_basis_points'] = MoneyFormatter::decimalToBasisPoints($data['service_charge_percent'] ?? '0.00');
            unset($data['service_charge_percent']);
            $data['service_modes'] = BranchServiceMode::normalizeList($data['service_modes'] ?? null);

            $settings->fill($data);
            $settings->save();

            $settings->branch()
                ->select(['id', 'currency'])
                ->update(['currency' => $data['default_currency']]);

            $this->forgetBranchCache->handle((int) $settings->branch_id);

            return $settings->refresh();
        });
    }
}
