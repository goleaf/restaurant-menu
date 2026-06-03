<?php

namespace App\Models;

use Database\Factories\MenuCategoryTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['menu_category_id', 'language_code', 'name', 'description'])]
class MenuCategoryTranslation extends Model
{
    /** @use HasFactory<MenuCategoryTranslationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }
}
