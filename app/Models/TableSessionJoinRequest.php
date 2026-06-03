<?php

namespace App\Models;

use App\Enums\TableSessionJoinRequestStatus;
use Database\Factories\TableSessionJoinRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['table_session_id', 'guest_name', 'guest_token', 'status', 'approved_by_guest_id', 'rejected_by_guest_id', 'approved_by_user_id', 'rejected_by_user_id', 'expires_at'])]
class TableSessionJoinRequest extends Model
{
    /** @use HasFactory<TableSessionJoinRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TableSessionJoinRequestStatus::class,
            'expires_at' => 'datetime',
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
     * @return BelongsTo<TableSessionGuest, $this>
     */
    public function approvedByGuest(): BelongsTo
    {
        return $this->belongsTo(TableSessionGuest::class, 'approved_by_guest_id');
    }

    /**
     * @return BelongsTo<TableSessionGuest, $this>
     */
    public function rejectedByGuest(): BelongsTo
    {
        return $this->belongsTo(TableSessionGuest::class, 'rejected_by_guest_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }
}
