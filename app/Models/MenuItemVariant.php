<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuItemVariantType;
use Database\Factories\MenuItemVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MenuItemVariantType $type
 * @property int $price_cents
 * @property string|null $weight
 * @property string|null $volume
 * @property bool $is_default
 * @property bool $is_available
 */
#[Fillable(['menu_item_id', 'type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])]
class MenuItemVariant extends Model
{
    /** @use HasFactory<MenuItemVariantFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => MenuItemVariantType::Variant->value,
        'price_cents' => 0,
        'is_default' => false,
        'is_available' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MenuItemVariantType::class,
            'price_cents' => 'integer',
            'weight' => 'decimal:2',
            'volume' => 'decimal:2',
            'is_default' => 'boolean',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id')->withTrashed();
    }

    /**
     * @return HasMany<MenuItemVariantTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuItemVariantTranslation::class);
    }

    public function localizedName(string $languageCode): string
    {
        $translation = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('language_code', $languageCode)?->name
            : $this->translations()
                ->select('name')
                ->where('language_code', $languageCode)
                ->value('name');

        return is_string($translation) && filled($translation) ? $translation : $this->name;
    }
}
