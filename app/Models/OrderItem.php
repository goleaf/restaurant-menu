<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'table_session_guest_id', 'menu_item_id', 'kitchen_department_id', 'kitchen_department_type', 'kitchen_department_name', 'guest_name', 'item_name', 'quantity', 'unit_price', 'modifier_total', 'total_price', 'selected_modifiers', 'comment'])]
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'modifier_total' => 'decimal:2',
            'total_price' => 'decimal:2',
            'selected_modifiers' => 'array',
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
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * @return BelongsTo<KitchenDepartment, $this>
     */
    public function kitchenDepartment(): BelongsTo
    {
        return $this->belongsTo(KitchenDepartment::class);
    }
}
