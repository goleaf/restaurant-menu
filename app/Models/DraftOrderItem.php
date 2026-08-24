<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuItemVariantType;
use Database\Factories\DraftOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $unit_price_cents
 * @property int $modifier_total_cents
 * @property int $total_price_cents
 * @property MenuItemVariantType|null $variant_type
 * @property-read DraftOrder $draftOrder
 * @property-read TableSessionGuest $guest
 */
#[Fillable(['draft_order_id', 'table_session_guest_id', 'menu_item_id', 'menu_item_variant_id', 'item_name', 'variant_name', 'variant_type', 'quantity', 'unit_price_cents', 'modifier_total_cents', 'total_price_cents', 'selected_modifiers', 'comment', 'idempotency_key'])]
class DraftOrderItem extends Model
{
    /** @use HasFactory<DraftOrderItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $hidden = ['idempotency_key'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit_price_cents' => 0,
        'modifier_total_cents' => 0,
        'total_price_cents' => 0,
        'selected_modifiers' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'modifier_total_cents' => 'integer',
            'total_price_cents' => 'integer',
            'selected_modifiers' => 'array',
            'variant_type' => MenuItemVariantType::class,
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
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    /**
     * @return BelongsTo<MenuItemVariant, $this>
     */
    public function menuItemVariant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class);
    }
}
