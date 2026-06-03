<?php

namespace App\Models;

use Database\Factories\MenuItemTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['menu_item_id', 'language_code', 'name', 'description'])]
class MenuItemTranslation extends Model
{
    /** @use HasFactory<MenuItemTranslationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
