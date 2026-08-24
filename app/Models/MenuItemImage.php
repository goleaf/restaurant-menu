<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MenuItemImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['menu_item_id', 'path', 'sort_order'])]
class MenuItemImage extends Model
{
    /** @use HasFactory<MenuItemImageFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id')->withTrashed();
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
