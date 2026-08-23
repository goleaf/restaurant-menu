<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuItemVariantType;
use Carbon\CarbonInterface;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonInterface|null $cancelled_at
 * @property int $unit_price_cents
 * @property int $unit_price_snapshot_cents
 * @property int $modifier_total_cents
 * @property int $total_price_cents
 * @property MenuItemVariantType|null $variant_type
 * @property-read User|null $cancelledByUser
 */
#[Fillable(['order_id', 'table_session_guest_id', 'menu_item_id', 'menu_item_variant_id', 'original_menu_item_id', 'kitchen_department_id', 'kitchen_department_type', 'kitchen_department_name', 'guest_name', 'guest_name_snapshot', 'item_name', 'item_name_snapshot', 'item_description_snapshot', 'variant_name', 'variant_type', 'quantity', 'unit_price_cents', 'unit_price_snapshot_cents', 'modifier_total_cents', 'total_price_cents', 'selected_modifiers', 'modifiers_snapshot', 'tax_snapshot', 'service_snapshot', 'comment', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit_price_cents' => 0,
        'unit_price_snapshot_cents' => 0,
        'modifier_total_cents' => 0,
        'total_price_cents' => 0,
        'selected_modifiers' => '[]',
        'modifiers_snapshot' => '[]',
        'tax_snapshot' => '[]',
        'service_snapshot' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'unit_price_snapshot_cents' => 'integer',
            'modifier_total_cents' => 'integer',
            'total_price_cents' => 'integer',
            'selected_modifiers' => 'array',
            'modifiers_snapshot' => 'array',
            'tax_snapshot' => 'array',
            'service_snapshot' => 'array',
            'variant_type' => MenuItemVariantType::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<TableSessionGuest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(TableSessionGuest::class, 'table_session_guest_id');
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    /**
     * @return BelongsTo<MenuItemVariant, $this>
     */
    public function menuItemVariant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class);
    }

    /**
     * @return BelongsTo<KitchenDepartment, $this>
     */
    public function kitchenDepartment(): BelongsTo
    {
        return $this->belongsTo(KitchenDepartment::class);
    }

    /**
     * @return HasOne<KitchenTicketItem, $this>
     */
    public function kitchenTicketItem(): HasOne
    {
        return $this->hasOne(KitchenTicketItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * @param  Builder<OrderItem>  $query
     * @return Builder<OrderItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    /**
     * @param  Builder<OrderItem>  $query
     * @return Builder<OrderItem>
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at');
    }

    public function historicalGuestName(): ?string
    {
        $guestName = $this->guest_name_snapshot ?? $this->guest_name;

        return is_string($guestName) && filled($guestName) ? $guestName : null;
    }

    public function historicalItemName(): string
    {
        $itemName = $this->item_name_snapshot ?? $this->item_name;

        if (filled($itemName)) {
            return $itemName;
        }

        return $this->item_name;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historicalModifiers(): array
    {
        $modifiers = $this->modifiers_snapshot ?? $this->selected_modifiers ?? [];

        return is_array($modifiers) ? $modifiers : [];
    }
}
