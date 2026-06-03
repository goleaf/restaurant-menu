<?php

namespace App\Models;

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\TableSessionStatus;
use Database\Factories\ServicePointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['branch_id', 'area_node_id', 'type', 'name', 'display_number', 'internal_code', 'capacity', 'icon', 'status', 'position_x', 'position_y', 'is_active', 'metadata'])]
class ServicePoint extends Model
{
    /** @use HasFactory<ServicePointFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'table',
        'capacity' => 1,
        'status' => 'free',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServicePointType::class,
            'capacity' => 'integer',
            'status' => ServicePointStatus::class,
            'position_x' => 'float',
            'position_y' => 'float',
            'is_active' => 'boolean',
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
     * @return BelongsTo<AreaNode, $this>
     */
    public function areaNode(): BelongsTo
    {
        return $this->belongsTo(AreaNode::class);
    }

    /**
     * @return HasMany<QrCode, $this>
     */
    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    /**
     * @return HasMany<TableSession, $this>
     */
    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /**
     * @return HasOne<TableSession, $this>
     */
    public function activeTableSession(): HasOne
    {
        return $this->hasOne(TableSession::class)
            ->where('status', TableSessionStatus::Active->value)
            ->oldest('started_at')
            ->oldest('id');
    }

    /**
     * @return HasOne<QrCode, $this>
     */
    public function activeQrCode(): HasOne
    {
        return $this->hasOne(QrCode::class)
            ->where('status', QrCodeStatus::Active->value);
    }
}
