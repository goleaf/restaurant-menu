<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MenuItemVariantTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['menu_item_variant_id', 'language_code', 'name'])]
class MenuItemVariantTranslation extends Model
{
    /** @use HasFactory<MenuItemVariantTranslationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MenuItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id');
    }
}
