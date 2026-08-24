<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MenuTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['menu_id', 'language_code', 'name'])]
class MenuTranslation extends Model
{
    /** @use HasFactory<MenuTranslationFactory> */
    use HasFactory;

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
