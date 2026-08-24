<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuStatus;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property MenuStatus $status
 */
#[Fillable(['name', 'sort_order'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MenuStatus::class,
            'sort_order' => 'integer',
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
     * @return HasMany<MenuCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<MenuTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuTranslation::class);
    }

    /**
     * @return HasMany<MenuAvailabilitySchedule, $this>
     */
    public function availabilitySchedules(): HasMany
    {
        return $this->hasMany(MenuAvailabilitySchedule::class)
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->orderBy('id');
    }
}
