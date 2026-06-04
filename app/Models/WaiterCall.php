<?php

namespace App\Models;

use App\Enums\WaiterCallStatus;
use Database\Factories\WaiterCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'service_point_id', 'active_service_point_id', 'table_session_id', 'requested_by_guest_id', 'status', 'requested_at', 'handled_at', 'handled_by_user_id', 'metadata'])]
class WaiterCall extends Model
{
    /** @use HasFactory<WaiterCallFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::saving(function (WaiterCall $waiterCall): void {
            $status = $waiterCall->status instanceof WaiterCallStatus
                ? $waiterCall->status
                : WaiterCallStatus::from($waiterCall->status ?? WaiterCallStatus::Pending->value);

            $waiterCall->active_service_point_id = $status === WaiterCallStatus::Pending
                ? $waiterCall->service_point_id
                : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WaiterCallStatus::class,
            'requested_at' => 'datetime',
            'handled_at' => 'datetime',
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
     * @return BelongsTo<ServicePoint, $this>
     */
    public function activeServicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class, 'active_service_point_id');
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /**
     * @return BelongsTo<TableSessionGuest, $this>
     */
    public function requestedByGuest(): BelongsTo
    {
        return $this->belongsTo(TableSessionGuest::class, 'requested_by_guest_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }
}
