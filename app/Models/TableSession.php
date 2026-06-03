<?php

namespace App\Models;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use Database\Factories\TableSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['branch_id', 'service_point_id', 'opened_by_user_id', 'opened_by_guest_id', 'guest_invite_token', 'guest_invite_created_at', 'guest_invite_created_by_guest_id', 'status', 'source', 'started_at', 'ended_at', 'closed_by_user_id', 'metadata'])]
class TableSession extends Model
{
    /** @use HasFactory<TableSessionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'source' => 'guest_created',
    ];

    protected static function booted(): void
    {
        static::saving(function (TableSession $tableSession): void {
            $status = $tableSession->status instanceof TableSessionStatus
                ? $tableSession->status
                : TableSessionStatus::from($tableSession->status ?? TableSessionStatus::Pending->value);

            $tableSession->active_service_point_id = $status === TableSessionStatus::Active
                ? $tableSession->service_point_id
                : null;

            $tableSession->pending_service_point_id = $status === TableSessionStatus::Pending
                ? $tableSession->service_point_id
                : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TableSessionStatus::class,
            'source' => TableSessionSource::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'guest_invite_created_at' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * @return BelongsTo<TableSessionGuest, $this>
     */
    public function openedByGuest(): BelongsTo
    {
        return $this->belongsTo(TableSessionGuest::class, 'opened_by_guest_id');
    }

    /**
     * @return BelongsTo<TableSessionGuest, $this>
     */
    public function guestInviteCreatedByGuest(): BelongsTo
    {
        return $this->belongsTo(TableSessionGuest::class, 'guest_invite_created_by_guest_id');
    }

    /**
     * @return HasMany<TableSessionGuest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(TableSessionGuest::class)
            ->orderBy('guest_name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<TableSessionGuest, $this>
     */
    public function activeGuests(): HasMany
    {
        return $this->hasMany(TableSessionGuest::class)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->orderBy('guest_name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<TableSessionJoinRequest, $this>
     */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(TableSessionJoinRequest::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasOne<DraftOrder, $this>
     */
    public function draftOrder(): HasOne
    {
        return $this->hasOne(DraftOrder::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function orderStatusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
