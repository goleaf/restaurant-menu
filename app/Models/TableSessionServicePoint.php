<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TableSessionServicePointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface $linked_at
 */
#[Fillable(['table_session_id', 'service_point_id', 'active_service_point_id', 'linked_by_user_id', 'linked_at', 'unlinked_by_user_id', 'unlinked_at'])]
class TableSessionServicePoint extends Model
{
    /** @use HasFactory<TableSessionServicePointFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (TableSessionServicePoint $link): void {
            $link->active_service_point_id = $link->unlinked_at === null
                ? $link->service_point_id
                : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'unlinked_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<TableSessionServicePoint>  $query
     * @return Builder<TableSessionServicePoint>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unlinked_at');
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
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
    public function linkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function unlinkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlinked_by_user_id');
    }
}
