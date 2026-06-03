<?php

namespace App\Models;

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use Database\Factories\ServicePointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
