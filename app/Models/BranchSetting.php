<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BranchOrderFlowMode;
use App\Enums\SupportedCurrency;
use Database\Factories\BranchSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $service_charge_basis_points
 * @property BranchOrderFlowMode $order_flow_mode
 * @property list<string> $service_modes
 */
#[Fillable([
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
])]
class BranchSetting extends Model
{
    /** @use HasFactory<BranchSettingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    private const array DEFAULT_ATTRIBUTES = [
        'require_waiter_confirmation_for_orders' => true,
        'allow_guest_created_sessions' => true,
        'allow_waiter_opened_sessions' => true,
        'allow_guest_invite_links' => true,
        'guest_join_requires_approval' => true,
        'polling_interval_seconds' => 1,
        'inactivity_warning_minutes' => 45,
        'pending_session_expire_minutes' => 30,
        'default_language' => 'en',
        'default_currency' => 'EUR',
        'service_charge_enabled' => false,
        'service_charge_basis_points' => 0,
        'tips_enabled' => false,
        'order_flow_mode' => 'waiter_confirmation',
        'service_modes' => '["dine_in"]',
    ];

    /** @var array<string, mixed> */
    protected $attributes = self::DEFAULT_ATTRIBUTES;

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
            'inactivity_warning_minutes' => 'integer',
            'pending_session_expire_minutes' => 'integer',
            'service_charge_enabled' => 'boolean',
            'service_charge_basis_points' => 'integer',
            'tips_enabled' => 'boolean',
            'order_flow_mode' => BranchOrderFlowMode::class,
            'service_modes' => 'array',
        ];
    }

    /**
     * Read legacy JSON-string-wrapped lists without changing persisted settings.
     * The native array cast continues to encode validated writes.
     *
     * @return Attribute<list<string>, never>
     */
    protected function serviceModes(): Attribute
    {
        return Attribute::get(function (?string $value): array {
            $modes = $this->fromJson($value);

            if (is_string($modes)) {
                $modes = $this->fromJson($modes);
            }

            return is_array($modes) ? array_values(array_filter($modes, is_string(...))) : [];
        });
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
     *     inactivity_warning_minutes: int,
     *     pending_session_expire_minutes: int,
     *     default_language: string,
     *     default_currency: string,
     *     service_charge_enabled: bool,
     *     service_charge_basis_points: int,
     *     tips_enabled: bool,
     *     order_flow_mode: string,
     *     service_modes: list<string>
     * }
     */
    public static function defaults(?Branch $branch = null): array
    {
        return [
            ...self::DEFAULT_ATTRIBUTES,
            'default_currency' => SupportedCurrency::normalize($branch?->currency),
            'service_modes' => json_decode(self::DEFAULT_ATTRIBUTES['service_modes'], true, flags: JSON_THROW_ON_ERROR),
        ];
    }
}
