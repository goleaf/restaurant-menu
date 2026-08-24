<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $price_cents
 * @property list<string> $allergens
 * @property list<string> $dietary_labels
 * @property string|null $weight
 * @property string|null $volume
 * @property int|null $kitchen_department_id
 * @property CarbonImmutable|null $hidden_until
 * @property-read MenuCategory $category
 * @property-read KitchenDepartment|null $kitchenDepartment
 */
#[Fillable(['menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'image', 'weight', 'volume', 'calories', 'is_available', 'hidden_until', 'sort_order'])]
class MenuItem extends Model
{
    public const MAX_IMAGES = 8;

    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'price_cents' => 0,
        'allergens' => '[]',
        'dietary_labels' => '[]',
        'is_available' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'allergens' => 'array',
            'dietary_labels' => 'array',
            'weight' => 'decimal:2',
            'volume' => 'decimal:2',
            'calories' => 'integer',
            'is_available' => 'boolean',
            'hidden_until' => 'immutable_datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class)->withTrashed();
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id')->withTrashed();
    }

    /**
     * @return BelongsTo<KitchenDepartment, $this>
     */
    public function kitchenDepartment(): BelongsTo
    {
        return $this->belongsTo(KitchenDepartment::class);
    }

    /**
     * @return HasMany<MenuItemTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuItemTranslation::class);
    }

    /**
     * @return HasMany<MenuItemImage, $this>
     */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(MenuItemImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<MenuItemVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<DraftOrderItem, $this>
     */
    public function draftOrderItems(): HasMany
    {
        return $this->hasMany(DraftOrderItem::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return BelongsToMany<ModifierGroup, $this>
     */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_item_modifier_groups')
            ->withTimestamps()
            ->orderBy('modifier_groups.sort_order')
            ->orderBy('modifier_groups.name')
            ->orderBy('modifier_groups.id');
    }

    public function imageUrl(): ?string
    {
        if (! is_string($this->image) || blank($this->image)) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }

    public function isTemporarilyHidden(?CarbonInterface $at = null): bool
    {
        if ($this->hidden_until === null) {
            return false;
        }

        return $this->hidden_until->isAfter($at ?? now());
    }
}
