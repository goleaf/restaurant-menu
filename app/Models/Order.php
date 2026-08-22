<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property OrderStatus $status
 * @property CarbonInterface|null $confirmed_at
 * @property array<string, mixed>|null $metadata
 * @property-read User|null $confirmedByUser
 */
#[Fillable(['service_point_id', 'table_session_id', 'draft_order_id', 'confirmed_by_user_id', 'confirmed_at', 'total_price', 'currency', 'metadata'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'confirmed_by_waiter',
        'total_price' => '0.00',
        'currency' => 'EUR',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'confirmed_at' => 'datetime',
            'total_price' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /**
     * @return BelongsTo<DraftOrder, $this>
     */
    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<KitchenTicket, $this>
     */
    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class)
            ->orderBy('department_name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
