<?php

namespace App\Models;

use App\Enums\TableSessionGuestStatus;
use Database\Factories\TableSessionGuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['table_session_id', 'name', 'status', 'joined_at', 'left_at', 'metadata'])]
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
}
