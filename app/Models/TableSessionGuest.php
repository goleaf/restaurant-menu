<?php

namespace App\Models;

use App\Enums\TableSessionGuestStatus;
use Database\Factories\TableSessionGuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['table_session_id', 'guest_name', 'guest_token', 'status', 'ready_at', 'joined_at', 'left_at', 'metadata'])]
class TableSessionGuest extends Model
{
    /** @use HasFactory<TableSessionGuestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TableSessionGuestStatus::class,
            'ready_at' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /**
     * @return HasMany<TableSessionJoinRequest, $this>
     */
    public function approvedJoinRequests(): HasMany
    {
        return $this->hasMany(TableSessionJoinRequest::class, 'approved_by_guest_id');
    }

    /**
     * @return HasMany<TableSessionJoinRequest, $this>
     */
    public function rejectedJoinRequests(): HasMany
    {
        return $this->hasMany(TableSessionJoinRequest::class, 'rejected_by_guest_id');
    }

    /**
     * @return HasMany<WaiterCall, $this>
     */
    public function waiterCalls(): HasMany
    {
        return $this->hasMany(WaiterCall::class, 'requested_by_guest_id')
            ->orderBy('requested_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<DraftOrderItem, $this>
     */
    public function draftOrderItems(): HasMany
    {
        return $this->hasMany(DraftOrderItem::class, 'table_session_guest_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'table_session_guest_id');
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function orderStatusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class, 'actor_guest_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
