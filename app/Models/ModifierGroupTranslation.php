<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModifierGroupTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['modifier_group_id', 'language_code', 'name'])]
class ModifierGroupTranslation extends Model
{
    /** @use HasFactory<ModifierGroupTranslationFactory> */
    use HasFactory;

    /** @return BelongsTo<ModifierGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
