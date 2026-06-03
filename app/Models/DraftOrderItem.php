<?php

namespace App\Models;

use Database\Factories\DraftOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['draft_order_id', 'table_session_guest_id', 'menu_item_id', 'item_name', 'quantity', 'unit_price', 'modifier_total', 'total_price', 'selected_modifiers', 'comment'])]
class DraftOrderItem extends Model
{
    /** @use HasFactory<DraftOrderItemFactory> */
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
     * @return BelongsTo<DraftOrder, $this>
     */
    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class);
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
}
