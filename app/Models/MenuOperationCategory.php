<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MenuOperationCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['menu_category_id', 'scan_cursor', 'discovered'])]
class MenuOperationCategory extends Model
{
    /** @use HasFactory<MenuOperationCategoryFactory> */
    use HasFactory;

    protected $attributes = ['scan_cursor' => 0, 'discovered' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scan_cursor' => 'integer', 'discovered' => 'boolean'];
    }

    /** @return BelongsTo<MenuOperation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(MenuOperation::class, 'menu_operation_id');
    }

    /** @return BelongsTo<MenuCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id')->withTrashed();
    }
}
