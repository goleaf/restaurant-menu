<?php

namespace App\Models;

use App\Enums\BranchOrderFlowMode;
use Database\Factories\BranchSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'require_waiter_confirmation_for_orders',
    'allow_guest_created_sessions',
    'allow_waiter_opened_sessions',
    'allow_guest_invite_links',
    'guest_join_requires_approval',
    'polling_interval_seconds',
    'default_language',
    'default_currency',
    'service_charge_enabled',
    'tips_enabled',
    'order_flow_mode',
])]
class BranchSetting extends Model
{
    /** @use HasFactory<BranchSettingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'require_waiter_confirmation_for_orders' => true,
        'allow_guest_created_sessions' => true,
        'allow_waiter_opened_sessions' => true,
        'allow_guest_invite_links' => true,
        'guest_join_requires_approval' => true,
        'polling_interval_seconds' => 1,
        'default_language' => 'en',
        'default_currency' => 'EUR',
        'service_charge_enabled' => false,
        'tips_enabled' => false,
        'order_flow_mode' => 'waiter_confirmation',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'require_waiter_confirmation_for_orders' => 'boolean',
            'allow_guest_created_sessions' => 'boolean',
            'allow_waiter_opened_sessions' => 'boolean',
            'allow_guest_invite_links' => 'boolean',
            'guest_join_requires_approval' => 'boolean',
            'polling_interval_seconds' => 'integer',
            'service_charge_enabled' => 'boolean',
            'tips_enabled' => 'boolean',
            'order_flow_mode' => BranchOrderFlowMode::class,
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return array{
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
     * }
     */
    public static function defaults(?Branch $branch = null): array
    {
        return [
            'require_waiter_confirmation_for_orders' => true,
            'allow_guest_created_sessions' => true,
            'allow_waiter_opened_sessions' => true,
            'allow_guest_invite_links' => true,
            'guest_join_requires_approval' => true,
            'polling_interval_seconds' => 1,
            'default_language' => 'en',
            'default_currency' => strtoupper($branch?->currency ?? 'EUR'),
            'service_charge_enabled' => false,
            'tips_enabled' => false,
            'order_flow_mode' => BranchOrderFlowMode::WaiterConfirmation->value,
        ];
    }
}
