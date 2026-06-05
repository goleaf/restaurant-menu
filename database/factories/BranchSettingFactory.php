<?php

namespace Database\Factories;

use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Models\Branch;
use App\Models\BranchSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

/**
 * @extends Factory<BranchSetting>
 */
class BranchSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            ...BranchSetting::defaults(),
        ];
    }

    public function demoReadyForService(Branch $branch): static
    {
        return $this
            ->for($branch)
            ->state(fn (): array => [
                ...BranchSetting::defaults($branch),
                'allow_guest_created_sessions' => true,
                'allow_waiter_opened_sessions' => true,
                'guest_join_requires_approval' => true,
                'allow_guest_invite_links' => true,
                'require_waiter_confirmation_for_orders' => true,
                'polling_interval_seconds' => 1,
                'default_language' => 'en',
                'default_currency' => $branch->currency,
                'service_charge_enabled' => false,
                'service_charge_percent' => '0.00',
                'tips_enabled' => false,
                'order_flow_mode' => BranchOrderFlowMode::WaiterConfirmation->value,
                'service_modes' => BranchServiceMode::defaultValues(),
                ...$this->optionalDemoSettings(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function optionalDemoSettings(): array
    {
        return collect([
            'allow_guest_bill_request' => true,
            'allow_guest_waiter_call' => true,
            'allow_repeat_orders_before_payment_request' => true,
            'manual_payment_only' => true,
        ])
            ->filter(fn (mixed $value, string $field): bool => Schema::hasColumn('branch_settings', $field))
            ->all();
    }
}
