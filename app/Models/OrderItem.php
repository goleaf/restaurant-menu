<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['order_id', 'table_session_guest_id', 'menu_item_id', 'original_menu_item_id', 'kitchen_department_id', 'kitchen_department_type', 'kitchen_department_name', 'guest_name', 'guest_name_snapshot', 'item_name', 'item_name_snapshot', 'item_description_snapshot', 'quantity', 'unit_price', 'unit_price_snapshot', 'modifier_total', 'total_price', 'selected_modifiers', 'modifiers_snapshot', 'tax_snapshot', 'service_snapshot', 'comment'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit_price' => '0.00',
        'modifier_total' => '0.00',
        'total_price' => '0.00',
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
            'unit_price' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'modifier_total' => 'decimal:2',
            'total_price' => 'decimal:2',
            'selected_modifiers' => 'array',
            'modifiers_snapshot' => 'array',
            'tax_snapshot' => 'array',
            'service_snapshot' => 'array',
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
