<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AreaNodeType;
use Database\Factories\AreaNodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property AreaNodeType $type
 */
#[Fillable(['parent_id', 'type', 'name', 'icon', 'sort_order', 'is_active', 'metadata'])]
class AreaNode extends Model
{
    /** @use HasFactory<AreaNodeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'custom',
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AreaNodeType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
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
     * @return BelongsTo<AreaNode, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /**
     * @return HasMany<AreaNode, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return HasMany<ServicePoint, $this>
     */
    public function servicePoints(): HasMany
    {
        return $this->hasMany(ServicePoint::class);
    }

    /**
     * @return HasMany<AreaNodeWaiter, $this>
     */
    public function waiterAssignments(): HasMany
    {
        return $this->hasMany(AreaNodeWaiter::class);
    }
}
