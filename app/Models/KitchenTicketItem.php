<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KitchenTicketItemStatus;
use Database\Factories\KitchenTicketItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property KitchenTicketItemStatus $status
 * @property-read KitchenTicket $kitchenTicket
 * @property-read TableSessionGuest|null $guest
 */
#[Fillable(['kitchen_ticket_id', 'order_item_id', 'table_session_guest_id', 'menu_item_id', 'guest_name', 'item_name', 'quantity', 'served_at', 'served_by_user_id', 'selected_modifiers', 'comment'])]
class KitchenTicketItem extends Model
{
    /** @use HasFactory<KitchenTicketItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'status' => 'new',
        'selected_modifiers' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => KitchenTicketItemStatus::class,
            'served_at' => 'datetime',
            'selected_modifiers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<KitchenTicket, $this>
     */
    public function kitchenTicket(): BelongsTo
    {
        return $this->belongsTo(KitchenTicket::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
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
     * @return BelongsTo<User, $this>
     */
    public function servedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by_user_id');
    }
}
