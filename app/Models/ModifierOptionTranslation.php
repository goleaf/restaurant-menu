<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModifierOptionTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['modifier_option_id', 'language_code', 'name'])]
class ModifierOptionTranslation extends Model
{
    /** @use HasFactory<ModifierOptionTranslationFactory> */
    use HasFactory;

    /** @return BelongsTo<ModifierOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }
}
